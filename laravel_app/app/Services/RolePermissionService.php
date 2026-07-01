<?php

namespace App\Services;

class RolePermissionService
{
    public function __construct(private InternalApiClient $client) {}

    public function indexData(): array
    {
        $data = $this->client->get('biz/role-permissions');
        $grouped = $this->groupFunctions($data['functions'] ?? []);
        $data['groupOrder'] = array_keys($grouped);
        return $data;
    }

    public function accountDetail(int $accountId): array
    {
        return $this->client->get("biz/role-permissions/accounts/{$accountId}");
    }

    public function updateRolePermissions(int $roleId, array $functionIds): void
    {
        $this->client->put("biz/role-permissions/roles/{$roleId}/permissions", ['function_ids' => $functionIds]);
    }

    public function listRoles(): array
    {
        return $this->client->get('biz/role-permissions/roles')['data'] ?? [];
    }

    public function storeRole(string $name): int
    {
        return (int) ($this->client->post('biz/role-permissions/roles', ['TenVaiTro' => $name])['id'] ?? 0);
    }

    public function destroyRole(int $roleId): void
    {
        $this->client->delete("biz/role-permissions/roles/{$roleId}");
    }

    public function assignAccountRole(int $maTK, int $maVaiTro): void
    {
        $this->client->post('biz/role-permissions/assign', ['MaTK' => $maTK, 'MaVaiTro' => $maVaiTro]);
    }

    public function revokeAccountRole(int $maTK, int $maVaiTro): void
    {
        $this->client->post('biz/role-permissions/revoke', ['MaTK' => $maTK, 'MaVaiTro' => $maVaiTro]);
    }

    public function groupFunctions(array $functions): array
    {
        $grouped = [];
        foreach ($functions as $fn) {
            $name = (string) ($fn['TenChucNang'] ?? '');
            $underscorePos = strpos($name, '_');
            $group = $underscorePos !== false ? substr($name, $underscorePos + 1) : $name;
            $grouped[$group][] = $fn;
        }
        ksort($grouped);
        return $grouped;
    }

    public function restoreDefaultPermissions(int $roleId): bool
    {
        try {
            $this->client->post("biz/role-permissions/roles/{$roleId}/restore-defaults");
            return true;
        } catch (\RuntimeException) {
            return false;
        }
    }
}
