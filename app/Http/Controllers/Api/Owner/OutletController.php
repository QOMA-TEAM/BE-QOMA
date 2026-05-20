<?php
namespace App\Http\Controllers\Api\Owner;
use App\Http\Controllers\Controller;
use App\Models\{Outlet, Usaha};
use App\Services\Owner\OutletService;
use App\Traits\HasPagination;
use Illuminate\Http\Request;

class OutletController extends Controller
{
    use HasPagination;
    public function __construct(private OutletService $service) {}

    public function index(Request $request, string $usahaId)
    {
        $this->gate($usahaId);
        return response()->json($this->paginateResponse(
            $this->service->getByUsaha($usahaId, $this->getPerPage($request)),
            'Daftar outlet'
        ));
    }

    public function store(Request $request, string $usahaId)
    {
        $this->gate($usahaId);
        $request->validate([
        'nama_outlet'  => 'required|string|max:100',
        'alamat'       => 'nullable|string',
        'email_outlet' => 'required|email|unique:users,email', 
        'username'     => 'required|string|min:4|unique:users,username',
        'password'     => 'required|string|min:6',
    ]);

        try {
            $result = $this->service->create($request->all(), $usahaId);
            return response()->json(['message' => 'Outlet dibuat. Akun outlet otomatis dibuat.', 'data' => $result], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function show(string $usahaId, string $id)
    {
        $outlet = $this->findOwned($usahaId, $id);
        return response()->json(['message' => 'Detail outlet', 'data' => $outlet->load('mejas', 'users')]);
    }

    public function update(Request $request, string $usahaId, string $id)
    {
        $outlet = $this->findOwned($usahaId, $id);
        $outlet = $this->service->update($outlet, $request->all());
        return response()->json(['message' => 'Outlet diupdate', 'data' => $outlet]);
    }

    public function toggleStatus(string $usahaId, string $id)
    {
        $outlet = $this->findOwned($usahaId, $id);
        $outlet = $this->service->toggleStatus($outlet);
        return response()->json(['message' => 'Status outlet diupdate', 'data' => $outlet]);
    }

    public function destroy(string $usahaId, string $id)
    {
        $outlet = $this->findOwned($usahaId, $id);
        $this->service->delete($outlet);
        return response()->json(['message' => 'Outlet dihapus']);
    }

    private function gate(string $usahaId): void
    {
        if (!Usaha::where('id', $usahaId)->where('owner_id', auth()->id())->exists()) abort(403, 'Bukan usaha Anda');
    }

    private function findOwned(string $usahaId, string $id): Outlet
    {
        $this->gate($usahaId);
        return Outlet::where('id', $id)->where('usaha_id', $usahaId)->firstOrFail();
    }

    // GET /owner/outlet — list semua outlet milik owner yang login
    public function myOutlets(Request $request)
    {
        $usahaId = auth()->user()->usaha_id;

        if (!$usahaId) {
            return response()->json(['message' => 'Owner belum memiliki usaha'], 404);
        }

        $outlets = Outlet::where('usaha_id', $usahaId)
                        ->withCount('mejas')
                        ->with(['users' => fn($q) => $q->select('id', 'outlet_id', 'username', 'is_active')])
                        ->paginate($this->getPerPage($request));

        return response()->json($this->paginateResponse($outlets, 'Daftar outlet saya'));
    }
}