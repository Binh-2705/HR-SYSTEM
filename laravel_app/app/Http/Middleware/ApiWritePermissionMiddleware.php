<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiWritePermissionMiddleware
{
    /**
     * @return array<int, string>
     */
    private function inferModulePermissions(Request $request): array
    {
        $module = (string) $request->route('module', '');
        if ($module === '') {
            return [];
        }

        $method = strtoupper($request->method());
        $permissionKey = match ($method) {
            'POST' => 'create',
            'PUT', 'PATCH' => 'update',
            'DELETE' => 'delete',
            default => null,
        };

        if ($permissionKey === null) {
            return [];
        }

        $permission = config("laravel_resource_modules.{$module}.permission.{$permissionKey}");
        if (!is_string($permission) || trim($permission) === '') {
            return [];
        }

        return [trim($permission)];
    }

    /**
     * @param  array<int, string>  $requiredPermissions
     */
    public function handle(Request $request, Closure $next, ...$requiredPermissions): mixed
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        $accountId = (int) $request->header('X-Account-Id', 0);
        $role = strtolower(trim((string) $request->header('X-Account-Role', '')));
        $permissionsHeader = trim((string) $request->header('X-Account-Permissions', ''));
        $permissions = array_values(array_filter(array_map('trim', explode(',', $permissionsHeader))));

        $hasUserContext = $accountId > 0 || $role !== '' || $permissions !== [];

        // Backward-compatible fallback: if request has no user context headers,
        // keep existing behavior (service token is still required by api.token).
        if (!$hasUserContext) {
            return $next($request);
        }

        if (in_array($role, ['admin', 'administrator', 'quantri', 'quan_tri'], true)) {
            return $next($request);
        }

        if ($requiredPermissions === []) {
            $requiredPermissions = $this->inferModulePermissions($request);
        }

        if ($requiredPermissions === []) {
            if ($permissions !== []) {
                return $next($request);
            }

            return new JsonResponse([
                'ok' => false,
                'message' => 'Forbidden: missing API write permission context.',
            ], 403);
        }

        $granted = false;
        foreach ($requiredPermissions as $requiredPermission) {
            if (in_array($requiredPermission, $permissions, true)) {
                $granted = true;
                break;
            }
        }

        if ($granted) {
            return $next($request);
        }

        return new JsonResponse([
            'ok' => false,
            'message' => 'Forbidden: insufficient API permissions.',
        ], 403);
    }
}
