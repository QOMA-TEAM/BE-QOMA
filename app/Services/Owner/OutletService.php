<?php
namespace App\Services\Owner;
use App\Models\{Menu, MenuOutlet, Outlet, Role, Subscription, User};
use App\Services\{ActivityLogService};
use Illuminate\Support\Facades\{DB, Hash};
use Illuminate\Support\Str;

class OutletService
{
    /**
     * Validasi apakah usaha masih boleh tambah outlet
     * berdasarkan plan subscription-nya
     */
    public function validateOutletLimit(string $usahaId): void
    {
        $subscription = Subscription::where('usaha_id', $usahaId)
                                    ->where('status', 'active')
                                    ->with('plan')
                                    ->latest()
                                    ->first();

        if (!$subscription) {
            throw new \Exception('Tidak ada subscription aktif.');
        }

        $batasOutlet  = $subscription->plan->batas_outlet;
        $jumlahOutlet = Outlet::where('usaha_id', $usahaId)->count();

        if ($batasOutlet !== -1 && $jumlahOutlet >= $batasOutlet) {

            // Bedakan pesan error berdasarkan plan
            if ($subscription->plan->is_lifetime) {
                // Sedang di Free plan
                throw new \Exception(
                    "Batas outlet Free plan adalah {$batasOutlet}. " .
                    "Anda memiliki {$jumlahOutlet} outlet. " .
                    "Upgrade ke Pro untuk menambah lebih banyak outlet."
                );
            } else {
                // Sedang di Pro tapi outlet sudah penuh (harusnya tidak terjadi karena unlimited)
                throw new \Exception("Batas outlet tercapai.");
            }
        }
    }

    public function getByUsaha(string $usahaId, int $perPage = 15)
    {
        return Outlet::select('id', 'usaha_id', 'nama_outlet', 'alamat', 'status_buka', 'email', 'created_at')
                    ->where('usaha_id', $usahaId)
                    ->withCount('mejas')
                    ->with(['users:id,outlet_id,username,email,is_active'])
                    ->paginate($perPage);
    }

    public function create(array $data, string $usahaId): array
    {
        return DB::transaction(function () use ($data, $usahaId) {
            $this->validateOutletLimit($usahaId);

            $outlet = Outlet::create([
                'id'          => Str::uuid(),
                'usaha_id'    => $usahaId,
                'nama_outlet' => $data['nama_outlet'],
                'alamat'      => $data['alamat'] ?? null,
                'status_buka' => true,
                'email'       => $data['email_outlet'], 
            ]);

            $role = Role::where('name', 'outlet')->first();

            $user = User::create([
                'id'           => Str::uuid(),
                'role_id'      => $role->id,
                'usaha_id'     => $usahaId,
                'outlet_id'    => $outlet->id,
                'username'     => $data['username'],
                'nama_lengkap' => $data['nama_outlet'],
                'email'        => $data['email_outlet'], 
                'password'     => Hash::make($data['password']),
                'is_active'    => true,
            ]);

            $this->syncMenuOutlet($outlet->id, $usahaId);

            ActivityLogService::log(
                'create_outlet',
                "Outlet '{$outlet->nama_outlet}' dibuat dengan akun '{$user->username}'",
                ['outlet_id' => $outlet->id],
                $usahaId,
                $outlet->id,
            );

            return [
                'outlet' => $outlet->load('usaha'),
                'akun'   => [
                    'username' => $user->username,
                    'email'    => $user->email,
                    'note'     => 'Akun outlet berhasil dibuat.',
                ],
            ];
        });
    }

    public function update(Outlet $outlet, array $data): Outlet
    {
        $outlet->update([
            'nama_outlet' => $data['nama_outlet'] ?? $outlet->nama_outlet,
            'alamat'      => $data['alamat']      ?? $outlet->alamat,
            'email'       => $data['email']       ?? $outlet->email,
        ]);

        ActivityLogService::log(
            'update_outlet',
            "Outlet '{$outlet->nama_outlet}' diupdate",
            [],
            $outlet->usaha_id,
            $outlet->id,
        );

        return $outlet->fresh();
    }

    public function toggleStatus(Outlet $outlet): Outlet
    {
        $outlet->update(['status_buka' => !$outlet->status_buka]);

        $status = $outlet->status_buka ? 'dibuka' : 'ditutup';
        ActivityLogService::log(
            'toggle_outlet_status',
            "Outlet '{$outlet->nama_outlet}' {$status}",
            [],
            $outlet->usaha_id,
            $outlet->id,
        );

        return $outlet->fresh();
    }

    public function delete(Outlet $outlet): void
    {
        DB::transaction(function () use ($outlet) {
            User::where('outlet_id', $outlet->id)->delete();
            $outlet->delete();
        });

        ActivityLogService::log(
            'delete_outlet',
            "Outlet '{$outlet->nama_outlet}' dihapus",
            [],
            $outlet->usaha_id,
        );
    }

    /**
     * Saat outlet baru dibuat → sync semua menu usaha ke menu_outlet
     */
    public function syncMenuOutlet(string $outletId, string $usahaId): void
    {
        Menu::where('usaha_id', $usahaId)->each(function ($menu) use ($outletId) {
            MenuOutlet::firstOrCreate(
                ['menu_id' => $menu->id, 'outlet_id' => $outletId],
                ['id' => Str::uuid(), 'harga' => $menu->harga_default, 'is_available' => true]
            );
        });
    }
}