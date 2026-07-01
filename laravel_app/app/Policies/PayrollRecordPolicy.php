<?php

namespace App\Policies;

use App\Models\User;

class PayrollRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasAny($user, ['xem_luong', 'tinh_luong_thang']);
    }

    public function create(User $user): bool
    {
        return $this->has($user, 'tinh_luong_thang');
    }

    public function update(User $user): bool
    {
        return $this->has($user, 'mo_chot_luong');
    }

    public function delete(User $user): bool
    {
        return $this->has($user, 'mo_chot_luong');
    }

    public function runMonthly(User $user): bool
    {
        return $this->has($user, 'tinh_luong_thang');
    }

    public function lock(User $user): bool
    {
        return $this->has($user, 'chot_luong');
    }

    public function unlock(User $user): bool
    {
        return $this->has($user, 'mo_chot_luong');
    }

    private function hasAny(User $user, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->has($user, $permission)) {
                return true;
            }
        }

        return false;
    }

    private function has(User $user, string $permission): bool
    {
        $permissions = $user->permissions ?? $user->Quyen ?? [];

        if (is_string($permissions)) {
            $permissions = array_filter(array_map('trim', explode(',', $permissions)));
        }

        return is_array($permissions) && in_array($permission, $permissions, true);
    }
}
