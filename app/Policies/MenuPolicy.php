<?php

namespace App\Policies;

use App\Models\Menu;
use App\Models\User;

class MenuPolicy
{
    public function before(User $user): ?bool
    {
        if ($user->role->name === 'super_admin') return true;
        return null;
    }

    public function manage(User $user, Menu $menu): bool
    {
        return $user->role->name === 'owner' && $menu->usaha->owner_id === $user->id;
    }

    // Admin cabang bisa edit harga menu_tenant milik cabangnya
    public function editHarga(User $user, Menu $menu): bool
    {
        if ($user->role->name !== 'admin_cabang') return false;

        return $menu->menuTenants()
                    ->where('tenant_id', $user->tenant_id)
                    ->exists();
    }
}
