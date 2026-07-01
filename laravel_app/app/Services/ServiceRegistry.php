<?php

namespace App\Services;

use InvalidArgumentException;

class ServiceRegistry
{
    public function getService(string $service): array
    {
        $services = config('service_registry.services', []);
        $definition = $services[$service] ?? null;

        if (!is_array($definition)) {
            throw new InvalidArgumentException("Service [{$service}] is not configured.");
        }

        return $definition;
    }

    public function getResource(string $service, string $resource): array
    {
        $serviceDefinition = $this->getService($service);
        $resourceDefinition = $serviceDefinition['resources'][$resource] ?? null;

        if (!is_array($resourceDefinition)) {
            throw new InvalidArgumentException("Resource [{$resource}] is not configured for service [{$service}].");
        }

        return array_merge($resourceDefinition, [
            'connection' => (string) ($serviceDefinition['connection'] ?? 'mysql'),
            'table' => (string) ($resourceDefinition['table'] ?? ''),
            'primary_key' => $resourceDefinition['primary_key'] ?? 'id',
            'read_only' => (bool) ($resourceDefinition['read_only'] ?? false),
        ]);
    }

    public function list(): array
    {
        return config('service_registry.services', []);
    }
}