<?php
namespace App\Http\Resources\Outlet;
use App\Models\StockMovement;
use Illuminate\Http\Resources\Json\JsonResource;

class BahanOutletResource extends JsonResource
{
    public function toArray($request): array
    {
        // Ambil batch aktif untuk bahan ini
        $batches = StockMovement::where('outlet_id', $this->outlet_id)
                                ->where('bahan_master_id', $this->bahan_master_id)
                                ->where('type', 'in')
                                ->where('is_finished', false)
                                ->where('remaining_quantity', '>', 0)
                                ->orderByRaw('CASE WHEN expired_date IS NULL THEN 1 ELSE 0 END')
                                ->orderBy('expired_date', 'asc')
                                ->get(['id', 'quantity', 'remaining_quantity', 'expired_date', 'created_at']);

        // Batch paling dekat expired
        $batchTerdekat = $batches->first();

        return [
            'id'            => $this->id,
            'stok'          => (float) $this->stok,
            'stok_minimum'  => (float) $this->stok_minimum,
            'is_menipis'    => $this->isMenipis(),

            // Info dari batch (FEFO)
            'batch_aktif'   => $batches->map(fn($b) => [
                'id'                 => $b->id,
                'jumlah_awal'        => (float) $b->quantity,
                'sisa'               => (float) $b->remaining_quantity,
                'expired_date'       => $b->expired_date?->format('Y-m-d'),
                'tanggal_masuk'      => $b->created_at->format('Y-m-d'),
                'mendekati_expired'  => $b->expired_date
                                          ? $b->expired_date->isPast() || $b->expired_date->diffInDays(now()) <= 3
                                          : false,
                'sudah_expired'      => $b->expired_date && $b->expired_date->isPast(),
            ]),

            'batch_terdekat_expired' => $batchTerdekat ? [
                'sisa'         => (float) $batchTerdekat->remaining_quantity,
                'expired_date' => $batchTerdekat->expired_date?->format('Y-m-d'),
            ] : null,

            'bahan_master' => $this->whenLoaded('bahanMaster', fn() => [
                'id'     => $this->bahanMaster->id,
                'nama'   => $this->bahanMaster->nama,
                'satuan' => $this->bahanMaster->satuan,
                'gambar' => $this->bahanMaster->gambar
                                ? asset('storage/' . $this->bahanMaster->gambar)
                                : null,
            ]),
        ];
    }
}