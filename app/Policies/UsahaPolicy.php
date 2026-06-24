<?php

namespace App\Policies;

use App\Models\Usaha;
use App\Models\User;

class UsahaPolicy
{
    /**
     * Super admin bisa lakukan apapun
     */
    public function before(User $user): ?bool
    {
        if ($user->role->name === 'super_admin') {
            return true; // bypass semua pengecekan policy di bawah
        }
        return null; // lanjut ke method policy berikutnya
    }

    public function viewAny(User $user): bool
    {
        return in_array($user->role->name, ['owner', 'super_admin']);
    }

    public function view(User $user, Usaha $usaha): bool
    {
        return $user->id === $usaha->owner_id;
    }

    public function create(User $user): bool
    {
        return $user->role->name === 'owner';
    }

    public function update(User $user, Usaha $usaha): bool
    {
        return $user->id === $usaha->owner_id;
    }

    public function delete(User $user, Usaha $usaha): bool
    {
        return $user->id === $usaha->owner_id;
    }
}
