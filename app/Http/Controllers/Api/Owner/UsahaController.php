<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\Usaha;
use App\Services\Owner\UsahaService;
use App\Traits\OwnerAccess;
use Illuminate\Http\Request;

class UsahaController extends Controller
{
    use OwnerAccess;

    public function __construct(private UsahaService $service) {}

    // GET /owner/usaha — info usaha milik owner yang login
    public function index()
    {
        $usahas = $this->service->getByOwner(auth()->id());

        return response()->json([
            'message' => 'Data usaha',
            'data'    => $usahas,
        ]);
    }

    // GET /owner/usaha/{id}
    public function show(string $id)
    {
        $usaha = Usaha::where('id', $id)
                      ->where('owner_id', auth()->id())
                      ->with(['outlets', 'subscription.plan'])
                      ->first();

        if (!$usaha) {
            return response()->json(['message' => 'Usaha tidak ditemukan'], 404);
        }

        return response()->json([
            'message' => 'Detail usaha',
            'data'    => $usaha,
        ]);
    }

    // PUT /owner/usaha/{id}
    public function update(Request $request, string $id)
    {
        $usaha = Usaha::where('id', $id)
                      ->where('owner_id', auth()->id())
                      ->first();

        if (!$usaha) {
            return response()->json(['message' => 'Usaha tidak ditemukan'], 404);
        }

        $request->validate([
            'nama_usaha' => 'sometimes|string|max:100',
            'alamat'     => 'nullable|string',
            'email'      => 'nullable|email',
        ]);

        $usaha = $this->service->update($usaha, $request->all());

        return response()->json([
            'message' => 'Usaha berhasil diupdate',
            'data'    => $usaha,
        ]);
    }
}
