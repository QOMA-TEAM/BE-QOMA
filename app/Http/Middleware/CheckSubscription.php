<?php

namespace App\Http\Middleware;

use App\Models\Subscription;
use Closure;
use Illuminate\Http\Request;

class CheckSubscription
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();

        if (!$user || $user->role->name !== 'owner') {
            return $next($request);
        }

        if (!$user->usaha_id) {
            return response()->json(['message' => 'Belum memiliki usaha.', 'code' => 'NO_USAHA'], 403);
        }

        $subscription = Subscription::where('usaha_id', $user->usaha_id)
                                    ->where('status', 'active')
                                    ->with('plan')
                                    ->latest()
                                    ->first();

        if (!$subscription) {
            return response()->json(['message' => 'Tidak ada subscription aktif.', 'code' => 'NO_SUBSCRIPTION'], 403);
        }

        if (!$subscription->plan->is_lifetime) {
            $today = now()->toDateString();

            // Cek apakah dalam grace period (expired tapi masih 3 hari)
            if ($subscription->end_date < $today) {
                if ($subscription->grace_period_end && $subscription->grace_period_end >= $today) {
                    // Masih dalam grace period — boleh akses tapi kasih warning di header
                    $request->merge([
                        '_subscription'  => $subscription,
                        '_grace_period'  => true,
                        '_sisa_grace'    => now()->diffInDays($subscription->grace_period_end),
                    ]);
                    return $next($request);
                }

                // Grace period juga sudah habis → downgrade
                $this->autoDowngradeToFree($subscription);

                $subscription = Subscription::where('usaha_id', $user->usaha_id)
                                            ->where('status', 'active')
                                            ->with('plan')
                                            ->latest()
                                            ->first();
            }
        }

        $request->merge(['_subscription' => $subscription]);
        return $next($request);
    }
    /**
     * Auto downgrade Pro → Free saat expired
     * Outlet lama tetap jalan, hanya blokir tambah baru
     */
    private function autoDowngradeToFree(Subscription $expiredSub): void
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($expiredSub) {

            // Tandai subscription lama sebagai expired
            $expiredSub->update(['status' => 'expired']);

            // Buat subscription Free yang baru (lifetime)
            Subscription::create([
                'id'         => \Illuminate\Support\Str::uuid(),
                'usaha_id'   => $expiredSub->usaha_id,
                'plan_id'    => 'plan_free',
                'start_date' => now()->toDateString(),
                'end_date'   => null,  // lifetime = tidak ada end date
                'status'     => 'active',
            ]);

            // Catat activity log
            \App\Services\ActivityLogService::log(
                'auto_downgrade_free',
                'Subscription Pro expired. Otomatis downgrade ke Free plan.',
                ['usaha_id' => $expiredSub->usaha_id],
                $expiredSub->usaha_id,
            );

            // Notif ke owner
            $usaha = \App\Models\Usaha::find($expiredSub->usaha_id);
            if ($usaha?->owner_id) {
                \App\Services\NotificationService::notify(
                    $usaha->owner_id,
                    'Subscription Expired',
                    'Subscription Pro Anda telah berakhir. Akun Anda kini menggunakan Free plan. Outlet yang sudah ada tetap berjalan, namun Anda tidak bisa menambah outlet baru. Upgrade kembali untuk fitur penuh.',
                    'subscription_expired',
                    ['usaha_id' => $expiredSub->usaha_id],
                );
            }

            // Notif ke super admin
            \App\Services\NotificationService::notifySuperAdmins(
                'Subscription Expired',
                "Usaha '{$usaha?->nama_usaha}' subscription Pro-nya expired dan otomatis downgrade ke Free.",
                'subscription_expired',
                ['usaha_id' => $expiredSub->usaha_id],
            );
        });
    }
}