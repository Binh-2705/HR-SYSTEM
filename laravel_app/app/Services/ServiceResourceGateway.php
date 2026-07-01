<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

class ServiceResourceGateway
{
    private const COMPOSITE_KEY_SEPARATOR = ',';

    public function __construct(private ServiceRegistry $serviceRegistry)
    {
    }

    public function catalog(): array
    {
        $services = [];

        foreach ($this->serviceRegistry->list() as $serviceName => $service) {
            $services[$serviceName] = [
                'connection' => $service['connection'] ?? null,
                'resources' => array_keys($service['resources'] ?? []),
            ];
        }

        return $services;
    }

    public function catalogForService(string $service): array
    {
        $serviceDefinition = $this->serviceRegistry->getService($service);

        return [
            'service' => $service,
            'connection' => $serviceDefinition['connection'] ?? null,
            'resources' => array_keys($serviceDefinition['resources'] ?? []),
        ];
    }

    public function describeResource(string $service, string $resource): array
    {
        $definition = $this->serviceRegistry->getResource($service, $resource);
        $columns = DB::connection($definition['connection'])
            ->select('SHOW COLUMNS FROM `' . $definition['table'] . '`');

        return [
            'service' => $service,
            'resource' => $resource,
            'connection' => $definition['connection'],
            'table' => $definition['table'],
            'primary_key' => $definition['primary_key'],
            'primary_key_label' => implode(', ', $this->primaryKeys($definition)),
            'read_only' => $this->isReadOnly($definition),
            'columns' => array_map(static function ($column) {
                $column = (array) $column;

                return [
                    'field' => (string) ($column['Field'] ?? ''),
                    'type' => (string) ($column['Type'] ?? 'text'),
                    'nullable' => (($column['Null'] ?? 'NO') === 'YES'),
                    'key' => (string) ($column['Key'] ?? ''),
                    'default' => $column['Default'] ?? null,
                    'extra' => (string) ($column['Extra'] ?? ''),
                ];
            }, $columns),
        ];
    }

    public function listRecords(string $service, string $resource, int $page, int $limit, string $keyword = '', array $extraFilters = []): array
    {
        $definition = $this->serviceRegistry->getResource($service, $resource);
        $columns = $this->columnsForDefinition($definition);
        $query = DB::connection($definition['connection'])->table($definition['table']);
        $this->applyKeywordFilter($query, $columns, $keyword);
        // Apply extra exact-match filters (e.g. ma_nv → MaNV)
        if (!empty($extraFilters['ma_nv'])) {
            $query->where('MaNV', (int) $extraFilters['ma_nv']);
        }
        $this->applyDefaultOrdering($query, $definition);
        $items = $query->forPage($page, $limit)->get()->map(function ($item) use ($definition) {
            return $this->withRecordIdentifier((array) $item, $definition);
        })->all();

        return [
            'service' => $service,
            'resource' => $resource,
            'connection' => $definition['connection'],
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => (clone $query)->count(),
            ],
            'data' => $items,
        ];
    }

    public function exportRecords(string $service, string $resource, string $keyword = ''): array
    {
        $definition = $this->serviceRegistry->getResource($service, $resource);
        $columns = $this->columnsForDefinition($definition);
        $query = DB::connection($definition['connection'])->table($definition['table']);
        $this->applyKeywordFilter($query, $columns, $keyword);
        $this->applyDefaultOrdering($query, $definition);

        return [
            'service' => $service,
            'resource' => $resource,
            'connection' => $definition['connection'],
            'columns' => array_map(static fn (array $column) => $column['field'], $columns),
            'rows' => $query->get()->map(function ($row) {
                return (array) $row;
            })->all(),
        ];
    }

    public function getRecord(string $service, string $resource, string $id): array
    {
        $definition = $this->serviceRegistry->getResource($service, $resource);
        $query = DB::connection($definition['connection'])->table($definition['table']);
        $this->applyRecordIdentifier($query, $definition, $id);
        $item = $query->first();

        if (!$item) {
            throw new InvalidArgumentException('Record not found.');
        }

        return [
            'service' => $service,
            'resource' => $resource,
            'connection' => $definition['connection'],
            'data' => $this->withRecordIdentifier((array) $item, $definition),
        ];
    }

    public function createRecord(string $service, string $resource, array $payload): array
    {
        $definition = $this->serviceRegistry->getResource($service, $resource);
        $this->guardWritable($definition);
        $connection = DB::connection($definition['connection']);
        $primaryKeys = $this->primaryKeys($definition);

        // Hash MatKhau for accounts resource before inserting
        if ($resource === 'accounts' && isset($payload['MatKhau']) && $payload['MatKhau'] !== '') {
            $payload['MatKhau'] = password_hash($payload['MatKhau'], PASSWORD_DEFAULT);
        }

        if (count($primaryKeys) === 1 && !array_key_exists($primaryKeys[0], $payload)) {
            $recordId = (string) $connection->table($definition['table'])->insertGetId($payload, $primaryKeys[0]);
        } else {
            $connection->table($definition['table'])->insert($payload);
            $recordId = $this->serializeRecordIdentifier($payload, $definition);
        }

        $record = $this->getRecord($service, $resource, $recordId);

        return array_merge($record, ['record_id' => $recordId]);
    }

    public function getRecordOrNull(string $service, string $resource, string $id): ?array
    {
        $definition = $this->serviceRegistry->getResource($service, $resource);
        $query = DB::connection($definition['connection'])->table($definition['table']);
        $this->applyRecordIdentifier($query, $definition, $id);
        $item = $query->first();

        if (!$item) {
            return null;
        }

        return [
            'service' => $service,
            'resource' => $resource,
            'connection' => $definition['connection'],
            'data' => $this->withRecordIdentifier((array) $item, $definition),
        ];
    }

    public function updateRecord(string $service, string $resource, string $id, array $payload): array
    {
        $definition = $this->serviceRegistry->getResource($service, $resource);
        $this->guardWritable($definition);
        foreach ($this->primaryKeys($definition) as $primaryKey) {
            unset($payload[$primaryKey]);
        }

        // Hash MatKhau for accounts resource if it's being updated
        if ($resource === 'accounts' && isset($payload['MatKhau']) && $payload['MatKhau'] !== '') {
            $payload['MatKhau'] = password_hash($payload['MatKhau'], PASSWORD_DEFAULT);
        }

        $connection = DB::connection($definition['connection']);
        $query = $connection->table($definition['table']);
        $this->applyRecordIdentifier($query, $definition, $id);
        $affected = $query->update($payload);

        if ($affected === 0) {
            $existingQuery = $connection->table($definition['table']);
            $this->applyRecordIdentifier($existingQuery, $definition, $id);
            $existing = $existingQuery->first();

            if (!$existing) {
                throw new InvalidArgumentException('Record not found.');
            }
        }

        $record = $this->getRecord($service, $resource, $id);

        return array_merge($record, ['record_id' => $id]);
    }

    public function deleteRecord(string $service, string $resource, string $id): array
    {
        $definition = $this->serviceRegistry->getResource($service, $resource);
        $this->guardWritable($definition);
        $query = DB::connection($definition['connection'])->table($definition['table']);
        $this->applyRecordIdentifier($query, $definition, $id);
        $deleted = $query->delete();

        if ($deleted === 0) {
            throw new InvalidArgumentException('Record not found.');
        }

        return [
            'service' => $service,
            'resource' => $resource,
            'connection' => $definition['connection'],
        ];
    }

    public function serializeRecordIdentifier(array $record, array $definition): string
    {
        $values = [];

        foreach ($this->primaryKeys($definition) as $primaryKey) {
            if (!array_key_exists($primaryKey, $record)) {
                throw new InvalidArgumentException("Missing primary key field [{$primaryKey}] in record payload.");
            }

            $values[] = (string) $record[$primaryKey];
        }

        return implode(self::COMPOSITE_KEY_SEPARATOR, $values);
    }

    private function primaryKeys(array $definition): array
    {
        $primaryKey = $definition['primary_key'] ?? 'id';

        return is_array($primaryKey) ? array_values($primaryKey) : [(string) $primaryKey];
    }

    private function isReadOnly(array $definition): bool
    {
        return (bool) ($definition['read_only'] ?? false);
    }

    private function columnsForDefinition(array $definition): array
    {
        $columns = DB::connection($definition['connection'])
            ->select('SHOW COLUMNS FROM `' . $definition['table'] . '`');

        return array_map(static function ($column) {
            $column = (array) $column;

            return [
                'field' => (string) ($column['Field'] ?? ''),
                'type' => (string) ($column['Type'] ?? 'text'),
                'nullable' => (($column['Null'] ?? 'NO') === 'YES'),
                'key' => (string) ($column['Key'] ?? ''),
                'default' => $column['Default'] ?? null,
                'extra' => (string) ($column['Extra'] ?? ''),
            ];
        }, $columns);
    }

    private function applyKeywordFilter(object $query, array $columns, string $keyword): void
    {
        if ($keyword === '') {
            return;
        }

        $searchableColumns = array_values(array_map(
            static fn (array $column) => (string) $column['field'],
            array_filter($columns, static fn (array $column) => !str_contains((string) $column['type'], 'blob'))
        ));

        if ($searchableColumns === []) {
            return;
        }

        $query->where(function ($inner) use ($searchableColumns, $keyword) {
            foreach ($searchableColumns as $index => $field) {
                $method = $index === 0 ? 'where' : 'orWhere';
                $inner->{$method}($field, 'like', "%{$keyword}%");
            }
        });
    }

    private function applyDefaultOrdering(object $query, array $definition): void
    {
        foreach ($this->primaryKeys($definition) as $primaryKey) {
            $query->orderBy($primaryKey);
        }
    }

    private function guardWritable(array $definition): void
    {
        if ($this->isReadOnly($definition)) {
            throw new LogicException('Resource is read-only.');
        }
    }

    private function applyRecordIdentifier(object $query, array $definition, string $id): void
    {
        $parts = explode(self::COMPOSITE_KEY_SEPARATOR, $id);
        $primaryKeys = $this->primaryKeys($definition);

        if (count($parts) !== count($primaryKeys)) {
            throw new InvalidArgumentException('Invalid record identifier.');
        }

        foreach ($primaryKeys as $index => $primaryKey) {
            $query->where($primaryKey, urldecode($parts[$index]));
        }
    }

    private function withRecordIdentifier(array $record, array $definition): array
    {
        $record['__resource_id'] = $this->serializeRecordIdentifier($record, $definition);

        return $record;
    }
}