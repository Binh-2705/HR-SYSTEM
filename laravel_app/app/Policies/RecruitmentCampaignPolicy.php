<?php

namespace App\Policies;

use App\Models\User;

class RecruitmentCampaignPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasAny($user, ['xem_tuyendung', 'them_dot_tuyen']);
    }

    public function create(User $user): bool
    {
        return $this->has($user, 'them_dot_tuyen');
    }

    public function update(User $user): bool
    {
        return $this->has($user, 'them_dot_tuyen');
    }

    public function delete(User $user): bool
    {
        return $this->has($user, 'xoa_dot_tuyen');
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
