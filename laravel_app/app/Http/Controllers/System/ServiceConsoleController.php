<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;

use App\Services\ServiceResourceGateway;
use App\Services\ServiceRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class ServiceConsoleController extends Controller
{
    public function __construct(
        private ServiceRegistry $serviceRegistry,
        private ServiceResourceGateway $gateway
    ) {
    }

    public function index(): View
    {
        return view('services.index', [
            'services' => $this->gateway->catalog(),
        ]);
    }

    public function show(Request $request, string $service, string $resource): View
    {
        $page = max(1, (int) $request->query('page', 1));
        $limit = max(1, min((int) $request->query('limit', 20), 100));

        try {
            $serviceConfig = $this->serviceRegistry->getService($service);
            $resourceConfig = $this->gateway->describeResource($service, $resource);
            $payload = $this->gateway->listRecords($service, $resource, $page, $limit);
        } catch (InvalidArgumentException $exception) {
            abort(404, $exception->getMessage());
        }

        return view('services.show', [
            'service' => $service,
            'resource' => $resource,
            'serviceConfig' => $serviceConfig,
            'resourceConfig' => $resourceConfig,
            'payload' => $payload,
            'apiTokenConfigured' => filled(config('services.service_gateway.token')),
        ]);
    }

    public function create(string $service, string $resource): View
    {
        try {
            $resourceConfig = $this->gateway->describeResource($service, $resource);
        } catch (InvalidArgumentException $exception) {
            abort(404, $exception->getMessage());
        }

        return view('services.form', [
            'mode' => 'create',
            'service' => $service,
            'resource' => $resource,
            'resourceConfig' => $resourceConfig,
            'record' => [],
            'recordId' => null,
        ]);
    }

    public function edit(string $service, string $resource, string $id): View
    {
        try {
            $resourceConfig = $this->gateway->describeResource($service, $resource);
            $recordPayload = $this->gateway->getRecord($service, $resource, $id);
        } catch (InvalidArgumentException $exception) {
            abort(404, $exception->getMessage());
        }

        return view('services.form', [
            'mode' => 'edit',
            'service' => $service,
            'resource' => $resource,
            'resourceConfig' => $resourceConfig,
            'record' => (array) $recordPayload['data'],
            'recordId' => $id,
        ]);
    }

    public function store(Request $request, string $service, string $resource): RedirectResponse
    {
        try {
            $resourceConfig = $this->gateway->describeResource($service, $resource);
            $payload = $this->buildPayloadFromRequest($request, $resourceConfig, false);
            $created = $this->gateway->createRecord($service, $resource, $payload);
            $recordId = (string) ($created['record_id'] ?? data_get((array) $created['data'], '__resource_id', ''));

            if ($recordId === '') {
                return redirect()->route('services.show', [
                    'service' => $service,
                    'resource' => $resource,
                ])->with('success', 'Đã tạo bản ghi thành công.');
            }

            return redirect()->route('services.edit', [
                'service' => $service,
                'resource' => $resource,
                'id' => $recordId,
            ])->with('success', 'Đã tạo bản ghi thành công.');
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['form' => $exception->getMessage()]);
        }
    }

    public function update(Request $request, string $service, string $resource, string $id): RedirectResponse
    {
        try {
            $resourceConfig = $this->gateway->describeResource($service, $resource);
            $payload = $this->buildPayloadFromRequest($request, $resourceConfig, true);
            $this->gateway->updateRecord($service, $resource, $id, $payload);

            return redirect()->route('services.edit', [
                'service' => $service,
                'resource' => $resource,
                'id' => $id,
                ])->with('success', 'Đã cập nhật bản ghi thành công.');
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['form' => $exception->getMessage()]);
        }
    }

    public function destroy(string $service, string $resource, string $id): RedirectResponse
    {
        try {
            $this->gateway->deleteRecord($service, $resource, $id);

            return redirect()->route('services.show', [
                'service' => $service,
                'resource' => $resource,
            ])->with('success', 'Đã xóa bản ghi thành công.');
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['form' => $exception->getMessage()]);
        }
    }

    private function buildPayloadFromRequest(Request $request, array $resourceConfig, bool $isUpdate): array
    {
        $payload = [];
        $primaryKeys = is_array($resourceConfig['primary_key'] ?? null)
            ? $resourceConfig['primary_key']
            : [(string) ($resourceConfig['primary_key'] ?? 'id')];

        foreach ($resourceConfig['columns'] as $column) {
            $field = (string) ($column['field'] ?? '');
            $isAutoIncrement = str_contains((string) ($column['extra'] ?? ''), 'auto_increment');

            if ($field === '' || in_array($field, $primaryKeys, true) || $isAutoIncrement) {
                continue;
            }

            if (!$request->exists($field)) {
                continue;
            }

            $value = $request->input($field);
            if ($value === '') {
                $payload[$field] = !empty($column['nullable']) ? null : $value;
                continue;
            }

            $payload[$field] = $value;
        }

        if ($payload === []) {
            throw new InvalidArgumentException($isUpdate
                ? 'Không có trường nào để cập nhật.'
                : 'Không có dữ liệu hợp lệ để tạo bản ghi.');
        }

        return $payload;
    }
}