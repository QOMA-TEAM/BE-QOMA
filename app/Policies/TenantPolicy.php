<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;

class TenantPolicy
{
    public function before(User $user): ?bool
    {
        if ($user->role->name === 'super_admin') return true;
        return null;
    }

    public function view(User $user, Tenant $tenant): bool
    {
        // Owner bisa lihat jika tenant milik usahanya
        if ($user->role->name === 'owner') {
            return $tenant->usaha->owner_id === $user->id;
        }
        // Admin cabang bisa lihat cabangnya sendiri
        if ($user->role->name === 'admin_cabang') {
            return $user->tenant_id === $tenant->id;
        }
        // Kasir bisa lihat cabangnya sendiri
        if ($user->role->name === 'kasir') {
            return $user->tenant_id === $tenant->id;
        }
        return false;
    }

    public function manage(User $user, Tenant $tenant): bool
    {
        return $user->role->name === 'owner' && $tenant->usaha->owner_id === $user->id;
    }
}
