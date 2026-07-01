<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Throwable;

class PermissionService
{
    public function __construct(private InternalApiClient $client) {}

    public function getPermissionsByAccountId(int $maTK): array
    {
        $cacheKey = "permissions_tk_{$maTK}";
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && $cached !== []) {
            return $cached;
        }

        if ($cached === []) {
            Cache::forget($cacheKey);
        }

        $permissions = $this->getPermissionsFromDatabase($maTK);

        $permissions = array_values(array_unique(array_filter(array_map(static fn ($name) => trim((string) $name), $permissions))));

        // Do not cache an empty set to avoid long-lived "no permission" UI during transient failures.
        if ($permissions !== []) {
            Cache::put($cacheKey, $permissions, 300);
        } else {
            Cache::forget($cacheKey);
        }

        return $permissions;
    }

    public function clearPermissionsCache(int $maTK): void
    {
        Cache::forget("permissions_tk_{$maTK}");
    }

    public function hasPermission(int $maTK, string $tenChucNang): bool
    {
        return in_array($tenChucNang, $this->getPermissionsByAccountId($maTK), true);
    }

    public function hasPermissionFromCache(int $maTK, string $tenChucNang): bool
    {
        return $this->hasPermission($maTK, $tenChucNang);
    }

    private function getPermissionsFromDatabase(int $maTK): array
    {
        if ($maTK <= 0) {
            return [];
        }

        try {
            return DB::connection($this->conn())
                ->table('taikhoanvaitro as tkvt')
                ->join('vaitro as vt', 'tkvt.MaVaiTro', '=', 'vt.MaVaiTro')
                ->join('phanquyen as pq', 'vt.MaVaiTro', '=', 'pq.MaVaiTro')
                ->join('chucnang as cn', 'pq.MaCN', '=', 'cn.MaCN')
                ->where('tkvt.MaTK', $maTK)
                ->pluck('cn.TenChucNang')
                ->toArray();
        } catch (Throwable) {
            return [];
        }
    }

    private function conn(): string
    {
        return (string) config('service_registry.services.hr.connection', config('database.default'));
    }
}
