<?php
namespace App\Services\Owner;
use App\Models\{Menu, MenuOutlet, Outlet, Role, Subscription, User};
use App\Services\{ActivityLogService, NotificationService};
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
        return Outlet::where('usaha_id', $usahaId)
                     ->withCount('mejas')
                     ->with(['users' => fn($q) => $q->select('id', 'outlet_id', 'username', 'is_active')])
                     ->paginate($perPage);
    }

    public function create(array $data, string $usahaId): array
    {
        return DB::transaction(function () use ($data, $usahaId) {
            // Cek limit outlet dari subscription
            $this->validateOutletLimit($usahaId);

            // Buat outlet
            $outlet = Outlet::create([
                'id'          => Str::uuid(),
                'usaha_id'    => $usahaId,
                'nama_outlet' => $data['nama_outlet'],
                'alamat'      => $data['alamat'] ?? null,
                'status_buka' => true,
                'email'       => $data['email'] ?? null,
            ]);

            // Auto generate akun outlet
            $role          = Role::where('name', 'outlet')->first();
            $suffix        = strtolower(Str::random(4));
            $slug          = strtolower(Str::slug($data['nama_outlet'], '_'));
            $username      = "outlet_{$slug}_{$suffix}";
            $plainPassword = Str::random(10);

            $user = User::create([
                'id'           => Str::uuid(),
                'role_id'      => $role->id,
                'usaha_id'     => $usahaId,
                'outlet_id'    => $outlet->id,
                'username'     => $username,
                'password'     => Hash::make($plainPassword),
                'email'        => $data['email'] ?? null,
                'is_active'    => true,
            ]);

            // Auto sync semua menu usaha ke outlet baru
            $this->syncMenuOutlet($outlet->id, $usahaId);

            ActivityLogService::log(
                'create_outlet',
                "Outlet '{$outlet->nama_outlet}' dibuat",
                ['outlet_id' => $outlet->id],
                $usahaId,
                $outlet->id,
            );

            return [
                'outlet' => $outlet->load('usaha'),
                'akun'   => [
                    'username' => $user->username,
                    'password' => $plainPassword,
                    'note'     => '⚠️ Simpan password ini! Tidak bisa ditampilkan lagi.',
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