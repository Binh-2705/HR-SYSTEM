<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class InternalApiClient
{
    private function defaultBaseUrl(): string
    {
        $configuredBaseUrl = trim((string) config('services.internal_api.default_base_url', ''));
        if ($configuredBaseUrl !== '') {
            return rtrim($configuredBaseUrl, '/');
        }

        if (function_exists('request') && app()->bound('request')) {
            $request = request();
            $scriptName = (string) ($request->server('SCRIPT_NAME') ?: $request->server('PHP_SELF', ''));
            $normalizedScript = str_replace('\\', '/', $scriptName);

            $basePath = '';
            if ($normalizedScript !== '' && $normalizedScript !== 'index.php' && $normalizedScript !== '/index.php') {
                $directory = str_replace('\\', '/', dirname($normalizedScript));
                if ($directory !== '.' && $directory !== '/' && $directory !== '\\') {
                    $basePath = rtrim($directory, '/');
                }
            }

            // Avoid inheriting the browser-facing mapped port (for example 8080 inside Docker).
            // Internal API calls should target the container's own web origin instead.
            return rtrim($request->getScheme() . '://' . $request->getHost() . $basePath, '/') . '/api';
        }

        return (string) config('services.internal_api.default_base_url', rtrim((string) config('app.url', 'http://localhost'), '/') . '/api');
    }

    private function baseUrl(string $path, ?string $forcedService = null): string
    {
        $service = $forcedService ?? $this->serviceFromPath($path);
        $defaultBaseUrl = $this->defaultBaseUrl();
        $serviceBaseUrl = is_string($service)
            ? (string) config("services.internal_api.endpoints.{$service}", '')
            : '';

        $baseUrl = $serviceBaseUrl !== '' ? $serviceBaseUrl : $defaultBaseUrl;

        return rtrim($baseUrl, '/');
    }

    private function serviceFromPath(string $path): ?string
    {
        $normalized = trim($path, '/');
        if ($normalized === '') {
            return null;
        }

        $segments = explode('/', $normalized);
        $first = $segments[0] ?? '';

        if ($first === 'modules') {
            $module = $segments[1] ?? '';
            if ($module !== '') {
                $service = config("laravel_resource_modules.{$module}.service");
                if (is_string($service) && $service !== '') {
                    return $service;
                }
            }

            return null;
        }

        if (in_array($first, ['hr', 'payroll', 'attendance', 'recruitment', 'training', 'chatbot'], true)) {
            return $first;
        }

        if ($first === 'reports') {
            return 'reporting';
        }

        if ($first !== 'biz') {
            return null;
        }

        $resource = $segments[1] ?? '';
        $bizMap = [
            'dashboard' => 'hr',
            'employees' => 'hr',
            'departments' => 'hr',
            'employee-profiles' => 'hr',
            'accounts' => 'hr',
            'role-permissions' => 'hr',
            'permissions' => 'hr',
            'leave-requests' => 'hr',
            'insurances' => 'hr',
            'audit-log' => 'hr',
            'search' => 'hr',
            'system-health' => 'hr',
            'attendance' => 'attendance',
            'payroll' => 'payroll',
            'contracts' => 'payroll',
            'recruitment' => 'recruitment',
            'training' => 'training',
            'reports' => 'reporting',
            'chatbot' => 'chatbot',
        ];

        return $bizMap[$resource] ?? null;
    }

    private function token(): string
    {
        return (string) config('services.service_gateway.token', '');
    }

    /**
     * @return array<string, string>
     */
    private function accountContextHeaders(): array
    {
        if (!function_exists('request') || !app()->bound('request')) {
            return [];
        }

        $request = request();
        $session = $request->hasSession() ? $request->session() : null;

        if ($session === null) {
            return [];
        }

        $account = (array) $session->get('taikhoan', []);
        $permissions = (array) $session->get('quyen', []);
        $accountId = (int) ($account['MaTK'] ?? $session->get('MaTK', 0));
        $role = trim((string) ($account['VaiTro'] ?? $account['role_name'] ?? ''));

        $headers = [];
        if ($accountId > 0) {
            $headers['X-Account-Id'] = (string) $accountId;
        }
        if ($role !== '') {
            $headers['X-Account-Role'] = $role;
        }

        $permissions = array_values(array_filter(array_map(static fn ($v) => trim((string) $v), $permissions)));
        if ($permissions !== []) {
            $headers['X-Account-Permissions'] = implode(',', $permissions);
        }

        return $headers;
    }

    private function client(int $timeout = 4): \Illuminate\Http\Client\PendingRequest
    {
        $defaultTimeout = (int) config('services.internal_api.timeout', 4);
        $connectTimeout = (int) config('services.internal_api.connect_timeout', 2);
        $resolvedTimeout = $timeout > 0 ? $timeout : $defaultTimeout;
        return Http::acceptJson()
            ->withHeaders($this->accountContextHeaders())
            ->withToken($this->token())
            ->connectTimeout(max(1, $connectTimeout))
            ->timeout(max(1, $resolvedTimeout));
    }

    // ─── Low-level HTTP verbs ─────────────────────────────────────────────────

    public function get(string $path, array $query = [], int $timeout = 4): array
    {
        return $this->getForService(null, $path, $query, $timeout);
    }

    public function getForService(?string $service, string $path, array $query = [], int $timeout = 4): array
    {
        try {
            $response = $this->client($timeout)->get($this->baseUrl($path, $service) . '/' . ltrim($path, '/'), $query);
            return $this->unwrap($response, $path);
        } catch (ConnectionException $e) {
            throw new RuntimeException("API connection failed [{$path}]: " . $e->getMessage(), 0, $e);
        }
    }

    public function post(string $path, array $payload = [], int $timeout = 4): array
    {
        return $this->postForService(null, $path, $payload, $timeout);
    }

    public function postForService(?string $service, string $path, array $payload = [], int $timeout = 4): array
    {
        try {
            $response = $this->client($timeout)->post($this->baseUrl($path, $service) . '/' . ltrim($path, '/'), $payload);
            return $this->unwrap($response, $path);
        } catch (ConnectionException $e) {
            throw new RuntimeException("API connection failed [{$path}]: " . $e->getMessage(), 0, $e);
        }
    }

    public function put(string $path, array $payload = [], int $timeout = 4): array
    {
        return $this->putForService(null, $path, $payload, $timeout);
    }

    public function putForService(?string $service, string $path, array $payload = [], int $timeout = 4): array
    {
        try {
            $response = $this->client($timeout)->put($this->baseUrl($path, $service) . '/' . ltrim($path, '/'), $payload);
            return $this->unwrap($response, $path);
        } catch (ConnectionException $e) {
            throw new RuntimeException("API connection failed [{$path}]: " . $e->getMessage(), 0, $e);
        }
    }

    public function patch(string $path, array $payload = [], int $timeout = 4): array
    {
        return $this->patchForService(null, $path, $payload, $timeout);
    }

    public function patchForService(?string $service, string $path, array $payload = [], int $timeout = 4): array
    {
        try {
            $response = $this->client($timeout)->patch($this->baseUrl($path, $service) . '/' . ltrim($path, '/'), $payload);
            return $this->unwrap($response, $path);
        } catch (ConnectionException $e) {
            throw new RuntimeException("API connection failed [{$path}]: " . $e->getMessage(), 0, $e);
        }
    }

    public function delete(string $path, int $timeout = 4): void
    {
        $this->deleteForService(null, $path, $timeout);
    }

    public function deleteForService(?string $service, string $path, int $timeout = 4): void
    {
        try {
            $response = $this->client($timeout)->delete($this->baseUrl($path, $service) . '/' . ltrim($path, '/'));
            $this->unwrap($response, $path);
        } catch (ConnectionException $e) {
            throw new RuntimeException("API connection failed [{$path}]: " . $e->getMessage(), 0, $e);
        }
    }

    public function paginate(string $path, array $payload = []): LengthAwarePaginator
    {
        $response = $this->post($path, $payload);
        $items = array_map(fn($item) => (object) $item, $response['data'] ?? []);

        return new LengthAwarePaginator(
            items: $items,
            total: (int) ($response['total'] ?? 0),
            perPage: (int) ($response['per_page'] ?? 15),
            currentPage: (int) ($response['current_page'] ?? 1),
            options: [
                'path' => \Illuminate\Pagination\Paginator::resolveCurrentPath(),
                'pageName' => 'page',
            ],
        );
    }

    // ─── Response unwrapper ───────────────────────────────────────────────────

    private function unwrap(\Illuminate\Http\Client\Response $response, string $path): array
    {
        if ($response->status() === 404) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException(
                "API resource not found [{$path}]"
            );
        }

        if (!$response->successful()) {
            $body = $response->json() ?? [];
            $message = (string) ($body['message'] ?? $response->body());
            throw new RuntimeException("API error [{$path}] HTTP {$response->status()}: {$message}");
        }

        $data = $response->json();
        if (!is_array($data)) {
            throw new RuntimeException("API returned non-JSON response [{$path}]");
        }

        return $data;
    }
}
