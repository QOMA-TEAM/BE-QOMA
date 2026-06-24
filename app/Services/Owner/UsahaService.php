<?php

namespace App\Services\Owner;

use App\Models\Usaha;

class UsahaService
{
    /**
     * Ambil usaha milik owner yang login
     */
    public function getByOwner(string $ownerId)
    {
        return Usaha::where('owner_id', $ownerId)
                    ->withCount('outlets')
                    ->with('subscription.plan')
                    ->get();
    }

    /**
     * Update data usaha
     */
    public function update(Usaha $usaha, array $data): Usaha
    {
        $usaha->update([
            'nama_usaha' => $data['nama_usaha'] ?? $usaha->nama_usaha,
            'alamat'     => $data['alamat']     ?? $usaha->alamat,
            'email'      => $data['email']      ?? $usaha->email,
        ]);

        return $usaha->fresh('outlets', 'subscription.plan');
    }
}
