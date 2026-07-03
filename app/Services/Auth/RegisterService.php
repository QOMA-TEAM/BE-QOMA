<?php
namespace App\Services\Auth;

use App\Models\{Plan, Subscription, Usaha, User};
use App\Services\{ActivityLogService, NotificationService};
use Illuminate\Support\Facades\{DB, Hash};
use Illuminate\Support\Str;

class RegisterService
{
    public function register(array $data): array
    {
        return DB::transaction(function () use ($data) {

            $plan = Plan::findOrFail($data['plan_id']);

            // 1. Buat user owner
            $user = User::create([
                'id'           => Str::uuid(),
                'role_id'      => 'role_owner',
                'username'     => $data['username'],
                'nama_lengkap' => $data['nama_owner'],
                'email'        => $data['email'],
                'no_telp'      => $data['no_telp'],     // ← BARU
                'password'     => Hash::make($data['password']),
                'is_active'    => DB::raw('false'),
            ]);

            // 2. Buat usaha
            $usaha = Usaha::create([
                'id'              => Str::uuid(),
                'nama_usaha'      => $data['nama_usaha'],
                'telp_usaha'      => $data['telp_usaha'],            // ← BARU
                'alamat'          => $data['alamat'] ?? null,
                'deskripsi_usaha' => $data['deskripsi_usaha'] ?? null, // ← BARU
                'email'           => $data['email'],
                'owner_id'        => $user->id,
                'status'          => 'pending',
            ]);

            $user->update(['usaha_id' => $usaha->id]);

            // 3. Subscription
            $subStatus = $plan->harga > 0 ? 'pending' : 'active';

            $subscription = Subscription::create([
                'id'         => Str::uuid(),
                'usaha_id'   => $usaha->id,
                'plan_id'    => $plan->id,
                'start_date' => now()->toDateString(),
                'end_date'   => $plan->is_lifetime ? null : now()->addDays($plan->durasi_hari)->toDateString(),
                'status'     => $subStatus,
                'tipe'       => 'new',
            ]);

            // 4. Notifikasi ke super admin
            try {
                NotificationService::notifySuperAdmins(
                    'Owner Baru Mendaftar',
                    "Owner '{$data['nama_owner']}' mendaftar dengan usaha '{$data['nama_usaha']}' (Plan: {$plan->nama_plan}).",
                    'new_owner_registration',
                    ['usaha_id' => $usaha->id, 'plan_name' => $plan->nama_plan]
                );
            } catch (\Exception $e) {
                \Log::warning('Notifikasi gagal saat register: ' . $e->getMessage());
            }

            // 5. Activity log
            ActivityLogService::log(
                'owner_register',
                "Owner '{$data['nama_owner']}' mendaftar dengan plan '{$plan->nama_plan}'",
                ['usaha_id' => $usaha->id],
                $usaha->id,
            );

            // 6. Response — siap di-redirect ke halaman pembayaran
            $response = [
                'message' => 'Pendaftaran berhasil! Lanjutkan ke pembayaran untuk mengaktifkan akun.',
                'user'    => [
                    'id'           => $user->id,
                    'username'     => $user->username,
                    'nama_lengkap' => $user->nama_lengkap,
                    'email'        => $user->email,
                    'no_telp'      => $user->no_telp,
                ],
                'usaha'   => [
                    'id'              => $usaha->id,
                    'nama_usaha'      => $usaha->nama_usaha,
                    'telp_usaha'      => $usaha->telp_usaha,
                    'alamat'          => $usaha->alamat,
                    'deskripsi_usaha' => $usaha->deskripsi_usaha,
                    'status'          => $usaha->status,
                ],
                'subscription' => [
                    'subscription_id' => $subscription->id, // ← dipakai untuk halaman pembayaran
                    'plan'            => $plan->nama_plan,
                    'harga'           => (float) $plan->harga,
                    'start_date'      => $subscription->start_date,
                    'end_date'        => $subscription->end_date,
                    'status'          => $subscription->status,
                ],
            ];

            // Kalau plan berbayar → arahkan ke halaman pembayaran
            if ($plan->harga > 0) {
                $response['redirect_to'] = 'pembayaran'; // ← signal untuk FE redirect
                $response['pembayaran'] = [
                    'metode'    => $data['metode_pembayaran'] ?? null,
                    'total'     => (float) $plan->harga,
                    'instruksi' => $data['metode_pembayaran'] === 'transfer'
                        ? 'Transfer ke BCA 1234567890 a/n PT QOMA INDONESIA.'
                        : 'Scan QRIS untuk melakukan pembayaran.',
                    'catatan'   => 'Akun aktif setelah pembayaran dikonfirmasi admin.',
                ];
            } else {
                // Free plan — tetap perlu approval admin meski tidak bayar
                $response['redirect_to'] = 'menunggu-approval';
            }

            return $response;
        });
    }
}
