<?php

namespace App\Http\Middleware;

use App\Models\{Outlet, OutletDeactivationQueue, Plan, Subscription};
use App\Services\{ActivityLogService, NotificationService};
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
            // Cek grace period — status masih aktif tapi mungkin belum diproses
            $graceSubscription = Subscription::where('usaha_id', $user->usaha_id)
                ->where('status', 'grace_period')
                ->with('plan')
                ->latest()
                ->first();

            if ($graceSubscription) {
                $subscription = $graceSubscription;
            } else {
                return response()->json(['message' => 'Tidak ada subscription aktif.', 'code' => 'NO_SUBSCRIPTION'], 403);
            }
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

                // Grace period juga sudah habis → cek kelebihan outlet
                $this->prosesGracePeriodHabis($subscription);

                // Cek apakah ada deactivation queue pending
                $queue = OutletDeactivationQueue::where('usaha_id', $user->usaha_id)
                    ->where('is_processed', 'false')
                    ->first();

                if ($queue) {
                    return response()->json([
                        'message' => 'Subscription Anda telah berakhir. Harap pilih outlet yang ingin dinonaktifkan.',
                        'code'    => 'NEED_DEACTIVATE_OUTLET',
                        'data'    => [
                            'jumlah_harus_nonaktif' => $queue->jumlah_harus_nonaktif,
                            'deadline'              => $queue->deadline,
                            'action_url'            => '/owner/subscription/pilih-outlet-nonaktif',
                        ],
                    ], 403);
                }

                // Tidak ada kelebihan → refresh subscription (sudah di-downgrade)
                $subscription = Subscription::where('usaha_id', $user->usaha_id)
                    ->where('status', 'active')
                    ->with('plan')
                    ->latest()
                    ->first();

                if (!$subscription) {
                    return response()->json(['message' => 'Tidak ada subscription aktif.', 'code' => 'NO_SUBSCRIPTION'], 403);
                }
            }
        }

        $request->merge(['_subscription' => $subscription]);
        return $next($request);
    }

    /**
     * Proses ketika grace period habis:
     * - Jika outlet melebihi batas free plan → buat deactivation queue
     * - Jika tidak melebihi → langsung downgrade ke free
     */
    private function prosesGracePeriodHabis(Subscription $expiredSub): void
    {
        // Cek apakah deactivation queue sudah ada
        $existingQueue = OutletDeactivationQueue::where('usaha_id', $expiredSub->usaha_id)
            ->where('is_processed', 'false')
            ->exists();

        if ($existingQueue) return;

        $freePlan = Plan::where('is_lifetime', 'true')
            ->orderBy('harga')
            ->first();

        if (!$freePlan) return;

        $jumlahOutlet    = Outlet::where('usaha_id', $expiredSub->usaha_id)->where('status_buka', 'true')->count();
        $kelebihanOutlet = max(0, $jumlahOutlet - $freePlan->batas_outlet);

        DB::transaction(function () use ($expiredSub, $freePlan, $kelebihanOutlet) {
            if ($kelebihanOutlet > 0) {
                // Perlu pilih outlet — buat deactivation queue
                $expiredSub->update(['status' => 'grace_period']);

                OutletDeactivationQueue::firstOrCreate(
                    ['usaha_id' => $expiredSub->usaha_id, 'is_processed' => false],
                    [
                        'id'                    => Str::uuid(),
                        'subscription_id'       => $expiredSub->id,
                        'jumlah_harus_nonaktif' => $kelebihanOutlet,
                        'deadline'              => now()->addHours(24),
                    ]
                );

                // Notif owner
                $usaha = \App\Models\Usaha::find($expiredSub->usaha_id);
                if ($usaha?->owner_id) {
                    NotificationService::notify(
                        $usaha->owner_id,
                        '🚨 Pilih Outlet yang Dinonaktifkan',
                        "Grace period berakhir. Anda harus memilih {$kelebihanOutlet} outlet yang akan dinonaktifkan dalam 24 jam. Jika tidak memilih, sistem akan otomatis menonaktifkan {$kelebihanOutlet} outlet terbaru.",
                        'harus_pilih_outlet',
                        ['usaha_id' => $expiredSub->usaha_id, 'jumlah_harus_nonaktif' => $kelebihanOutlet],
                    );
                }

                ActivityLogService::log(
                    'grace_period_habis_buat_queue',
                    "Grace period habis. Deactivation queue dibuat: {$kelebihanOutlet} outlet harus dipilih.",
                    ['usaha_id' => $expiredSub->usaha_id],
                    $expiredSub->usaha_id,
                );
            } else {
                // Tidak ada kelebihan → langsung downgrade
                $expiredSub->update(['status' => 'expired']);

                Subscription::create([
                    'id'         => Str::uuid(),
                    'usaha_id'   => $expiredSub->usaha_id,
                    'plan_id'    => $freePlan->id,
                    'start_date' => now()->toDateString(),
                    'end_date'   => null,
                    'status'     => 'active',
                    'tipe'       => 'downgrade',
                ]);

                // Notif owner
                $usaha = \App\Models\Usaha::find($expiredSub->usaha_id);
                if ($usaha?->owner_id) {
                    NotificationService::notify(
                        $usaha->owner_id,
                        'Subscription Expired — Downgrade ke Free',
                        'Subscription Anda telah berakhir dan otomatis beralih ke Free plan. Outlet yang ada tetap berjalan, namun Anda tidak bisa menambah outlet baru.',
                        'subscription_expired',
                        ['usaha_id' => $expiredSub->usaha_id],
                    );
                }

                ActivityLogService::log(
                    'auto_downgrade_free',
                    'Grace period habis. Otomatis downgrade ke Free plan (tidak ada kelebihan outlet).',
                    ['usaha_id' => $expiredSub->usaha_id],
                    $expiredSub->usaha_id,
                );
            }
        });
    }
}
