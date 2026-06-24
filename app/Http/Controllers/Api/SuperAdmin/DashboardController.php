<?php
namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\SuperAdmin\DashboardService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private DashboardService $service) {}

    // GET /super-admin/dashboard
    public function index()
    {
        return response()->json([
            'message' => 'Dashboard Super Admin',
            'data'    => $this->service->getStats(),
        ]);
    }

    // GET /super-admin/dashboard/mrr?filter=daily|weekly|monthly
    public function mrr(Request $request)
    {
        $filter = $request->get('filter', 'monthly');

        if (!in_array($filter, ['daily', 'weekly', 'monthly'])) {
            return response()->json(['message' => 'Filter tidak valid. Gunakan: daily, weekly, monthly'], 422);
        }

        return response()->json([
            'message' => 'MRR Graph Data',
            'data'    => $this->service->getMRR($filter),
        ]);
    }
}
