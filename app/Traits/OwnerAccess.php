<?php

namespace App\Traits;

use App\Models\Usaha;
use Illuminate\Http\JsonResponse;

trait OwnerAccess
{
    /**
     * Ambil usaha_id dari user yang sedang login.
     * Throw 403 kalau owner belum punya usaha.
     */
    protected function getUsahaId(): string
    {
        $usahaId = auth()->user()->usaha_id;

        if (!$usahaId) {
            abort(response()->json([
                'message' => 'Owner belum memiliki usaha.',
                'code'    => 'NO_USAHA',
            ], 403));
        }

        return $usahaId;
    }

    /**
     * Validasi bahwa resource (outlet, menu, dll) milik usaha owner ini.
     * Gunakan di setiap show/update/delete.
     */
    protected function validateMilikUsaha(string $model, string $id, string $foreignKey = 'usaha_id'): mixed
    {
        $usahaId = $this->getUsahaId();

        $record = $model::where('id', $id)
                        ->where($foreignKey, $usahaId)
                        ->first();

        if (!$record) {
            abort(response()->json([
                'message' => 'Data tidak ditemukan atau bukan milik usaha Anda.',
                'code'    => 'NOT_FOUND',
            ], 404));
        }

        return $record;
    }
}
