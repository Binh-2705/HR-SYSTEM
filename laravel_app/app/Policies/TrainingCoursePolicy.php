<?php

namespace App\Policies;

use App\Models\User;

class TrainingCoursePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasAny($user, ['xem_daotao', 'them_khoa_dao_tao']);
    }

    public function create(User $user): bool
    {
        return $this->has($user, 'them_khoa_dao_tao');
    }

    public function update(User $user): bool
    {
        return $this->has($user, 'them_khoa_dao_tao');
    }

    public function delete(User $user): bool
    {
        return $this->has($user, 'xoa_khoa_dao_tao');
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
