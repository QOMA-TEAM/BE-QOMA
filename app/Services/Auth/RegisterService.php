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

            $user = User::create([
                'id'           => Str::uuid(),
                'role_id'      => 'role_owner',
                'username'     => $data['username'],
                'nama_lengkap' => $data['nama_owner'],
                'email'        => $data['email'],       // ← wajib
                'password'     => Hash::make($data['password']),
                'is_active'    => false,
            ]);

            $usaha = Usaha::create([
                'id'         => Str::uuid(),
                'nama_usaha' => $data['nama_usaha'],
                'alamat'     => $data['alamat'] ?? null,
                'email'      => $data['email'],
                'owner_id'   => $user->id,
                'status'     => 'pending',
            ]);

            $user->update(['usaha_id' => $usaha->id]);

            $subStatus = $plan->harga > 0 ? 'pending' : 'active';

            $subscription = Subscription::create([
                'id'         => Str::uuid(),
                'usaha_id'   => $usaha->id,
                'plan_id'    => $plan->id,
                'start_date' => now()->toDateString(),
                'end_date'   => $plan->is_lifetime ? null : now()->addDays($plan->durasi_hari)->toDateString(),
                'status'     => $subStatus,
            ]);

            try {
                NotificationService::notifySuperAdmins(
                    'Owner Baru Mendaftar',
                    "Owner '{$data['nama_owner']}' mendaftar dengan usaha '{$data['nama_usaha']}' (Plan: {$plan->nama_plan}).",
                    'new_owner_registration',
                    [
                        'usaha_id' => $usaha->id,
                        'plan_name' => $plan->nama_plan
                    ]
                );
            } catch (\Exception $e) {
                \Log::warning(
                    'Notifikasi gagal saat register: ' .
                    $e->getMessage()
                );
            };

            ActivityLogService::log(
                'owner_register',
                "Owner '{$data['nama_owner']}' mendaftar dengan plan '{$plan->nama_plan}'",
                ['usaha_id' => $usaha->id],
                $usaha->id,
            );

            $response = [
                'message'      => 'Pendaftaran berhasil! Menunggu persetujuan admin.',
                'user'         => [
                    'id'           => $user->id,
                    'username'     => $user->username,
                    'nama_lengkap' => $user->nama_lengkap,
                    'email'        => $user->email,
                ],
                'usaha'        => [
                    'id'         => $usaha->id,
                    'nama_usaha' => $usaha->nama_usaha,
                    'status'     => $usaha->status,
                ],
                'subscription' => [
                    'plan'       => $plan->nama_plan,
                    'harga'      => (float) $plan->harga,
                    'start_date' => $subscription->start_date,
                    'end_date'   => $subscription->end_date,
                    'status'     => $subscription->status,
                ],
            ];

            if ($plan->harga > 0) {
                $response['pembayaran'] = [
                    'metode'   => $data['metode_pembayaran'] ?? null,
                    'total'    => (float) $plan->harga,
                    'instruksi'=> $data['metode_pembayaran'] === 'transfer'
                        ? 'Transfer ke BCA 1234567890 a/n PT QOMA INDONESIA.'
                        : 'Scan QRIS untuk melakukan pembayaran.',
                    'catatan'  => 'Akun aktif setelah pembayaran dikonfirmasi admin.',
                ];
            }

            return $response;
        });
    }
}