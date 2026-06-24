<?php
namespace App\Traits;

trait OutletAccess
{
    /**
     * Ambil outlet_id dari user yang sedang login.
     */
    protected function getOutletId(): string
    {
        $outletId = auth()->user()->outlet_id;

        if (!$outletId) {
            abort(response()->json([
                'message' => 'User tidak terhubung ke outlet manapun.',
                'code'    => 'NO_OUTLET',
            ], 403));
        }

        return $outletId;
    }

    /**
     * Ambil outlet beserta status_buka.
     * Throw 403 kalau outlet sedang tutup dan $requireOpen = true.
     */
    protected function getOutlet(bool $requireOpen = false): \App\Models\Outlet
    {
        $outletId = $this->getOutletId();
        $outlet   = \App\Models\Outlet::findOrFail($outletId);

        if ($requireOpen && !$outlet->status_buka) {
            abort(response()->json([
                'message' => 'Outlet sedang tutup. Tidak bisa menerima pesanan baru.',
                'code'    => 'OUTLET_CLOSED',
            ], 403));
        }

        return $outlet;
    }
}
