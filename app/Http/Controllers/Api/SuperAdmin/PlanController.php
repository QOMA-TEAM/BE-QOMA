<?php
namespace App\Http\Controllers\Api\SuperAdmin;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Services\SuperAdmin\UsahaManagementService;
use App\Traits\HasPagination;
use Illuminate\Http\Request;
use App\Http\Resources\SuperAdmin\PlanResource;

class PlanController extends Controller
{
    use HasPagination;
    public function __construct(private UsahaManagementService $service) {}

    public function index()
    {
        return response()->json([
            'message' => 'Daftar plan',
            'data'    => PlanResource::collection($this->service->listPlans()),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nama_plan'    => 'required|string',
            'harga'        => 'required|numeric|min:0',
            'batas_outlet' => 'required|integer|min:-1',
            'durasi_hari'  => 'required|integer|min:1',
            'deskripsi'    => 'nullable|string',
        ]);

        $plan = $this->service->createPlan($request->all());
        return response()->json(['message' => 'Plan dibuat', 'data' =>new PlanResource($plan)], 201);
    }

    public function update(Request $request, string $id)
    {
        $request->validate([
            'nama_plan'    => 'sometimes|string',
            'harga'        => 'sometimes|numeric|min:0',
            'batas_outlet' => 'sometimes|integer|min:-1',
            'durasi_hari'  => 'required|integer|min:1',
            'deskripsi'    => 'nullable|string',
        ]);

        $plan = Plan::findOrFail($id);
        $plan = $this->service->updatePlan($plan, $request->all());
        return response()->json(['message' => 'Plan diupdate', 'data' => new PlanResource($plan)]);
    }

    public function destroy(string $id)
    {
        $plan = Plan::findOrFail($id);
        try {
            $this->service->deletePlan($plan);
            return response()->json(['message' => 'Plan dihapus']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function show(string $id)
    {
        return response()->json([
            'message' => 'Detail plan',
            'data'    => Plan::withCount('subscriptions')->findOrFail($id),
        ]);
    }
}