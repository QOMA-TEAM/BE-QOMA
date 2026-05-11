<?php
namespace App\Services\SuperAdmin;

use App\Models\{ActivityLog, Outlet, Subscription, Usaha, User};
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /**
     * Data utama dashboard
     */
    public function getStats(): array
    {
        // Total pendapatan dari semua subscription aktif
        $totalPendapatanSubscription = Subscription::where('status', 'active')
            ->join('plans', 'subscriptions.plan_id', '=', 'plans.id')
            ->sum('plans.harga');

        // Total pendapatan bulan ini
        $totalPendapatanBulanIni = Subscription::where('status', 'active')
            ->whereMonth('start_date', now()->month)
            ->whereYear('start_date', now()->year)
            ->join('plans', 'subscriptions.plan_id', '=', 'plans.id')
            ->sum('plans.harga');

        return [
            'ringkasan' => [
                'total_usaha'                    => Usaha::count(),
                'total_outlet'                   => Outlet::count(),
                'total_pendapatan_subscription'  => (float) $totalPendapatanSubscription,
                'total_pendapatan_bulan_ini'     => (float) $totalPendapatanBulanIni,
                'total_owner'                    => User::whereHas('role', fn($q) => $q->where('name', 'owner'))->count(),
            ],
            'usaha_by_status' => [
                'pending'   => Usaha::where('status', 'pending')->count(),
                'active'    => Usaha::where('status', 'active')->count(),
                'suspended' => Usaha::where('status', 'suspended')->count(),
                'rejected'  => Usaha::where('status', 'rejected')->count(),
            ],
            'subscription_by_plan' => Subscription::where('status', 'active')
                ->join('plans', 'subscriptions.plan_id', '=', 'plans.id')
                ->select('plans.nama_plan', DB::raw('COUNT(*) as total'), DB::raw('SUM(plans.harga) as pendapatan'))
                ->groupBy('plans.id', 'plans.nama_plan')
                ->get(),

            'pending_approvals' => Usaha::where('status', 'pending')
                ->with('owner:id,username,nama_lengkap,email')
                ->latest()
                ->limit(5)
                ->get(['id', 'nama_usaha', 'email', 'owner_id', 'created_at']),

            'recent_activities' => ActivityLog::with('user:id,username')
                ->latest()
                ->limit(10)
                ->get(['id', 'user_id', 'aktivitas', 'deskripsi', 'created_at']),
        ];
    }

    /**
     * MRR Graph — Monthly Recurring Revenue
     *
     * @param string $filter  'daily' | 'weekly' | 'monthly'
     */
    public function getMRR(string $filter = 'monthly'): array
    {
        $query = Subscription::join('plans', 'subscriptions.plan_id', '=', 'plans.id')
                             ->where('subscriptions.status', 'active');

        switch ($filter) {
            case 'daily':
                // 30 hari terakhir, group per hari
                $data = $query->where('subscriptions.start_date', '>=', now()->subDays(30))
                    ->select(
                        DB::raw('DATE(subscriptions.start_date) as tanggal'),
                        DB::raw('SUM(plans.harga) as total'),
                        DB::raw('COUNT(*) as jumlah_subscriber')
                    )
                    ->groupBy('tanggal')
                    ->orderBy('tanggal')
                    ->get();

                return [
                    'filter'      => 'daily',
                    'label'       => '30 Hari Terakhir',
                    'data'        => $data,
                ];

            case 'weekly':
                // 7 hari terakhir, group per hari
                $data = $query->where('subscriptions.start_date', '>=', now()->subDays(7))
                    ->select(
                        DB::raw('DATE(subscriptions.start_date) as tanggal'),
                        DB::raw('SUM(plans.harga) as total'),
                        DB::raw('COUNT(*) as jumlah_subscriber')
                    )
                    ->groupBy('tanggal')
                    ->orderBy('tanggal')
                    ->get();

                return [
                    'filter' => 'weekly',
                    'label'  => '7 Hari Terakhir',
                    'data'   => $data,
                ];

            case 'monthly':
            default:
                // 12 bulan terakhir, group per bulan
                $data = $query->where('subscriptions.start_date', '>=', now()->subMonths(12))
                    ->select(
                        DB::raw("TO_CHAR(subscriptions.start_date, 'YYYY-MM') as bulan"),
                        DB::raw('SUM(plans.harga) as total'),
                        DB::raw('COUNT(*) as jumlah_subscriber')
                    )
                    ->groupBy('bulan')
                    ->orderBy('bulan')
                    ->get();

                return [
                    'filter' => 'monthly',
                    'label'  => '12 Bulan Terakhir',
                    'data'   => $data,
                ];
        }
    }
}