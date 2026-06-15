<?php
namespace App\Http\Resources\Outlet;
use Illuminate\Http\Resources\Json\JsonResource;

class BahanOutletResource extends JsonResource
{
    public function toArray($request): array
    {
        $satuan      = $this->bahanMaster->satuan ?? 'gram';
        $satuanDasar = $this->bahanMaster->satuan_dasar ?? 'gram';
        $konversi    = (float) ($this->bahanMaster->konversi_ke_dasar ?? 1);

        // Tampilkan stok dalam satuan besar (kg) sekaligus satuan dasar (gram)
        $stokDasar    = (float) $this->stok;
        $stokDisplay  = $konversi > 1 ? round($stokDasar / $konversi, 3) : $stokDasar;
        $minimumDasar = (float) $this->stok_minimum;
        $minimumDisplay = $konversi > 1 ? round($minimumDasar / $konversi, 3) : $minimumDasar;

        // Batch aktif dari stock_movements
        $batches = \App\Models\StockMovement::where('outlet_id', $this->outlet_id)
                                            ->where('bahan_master_id', $this->bahan_master_id)
                                            ->where('type', 'in')
                                            ->where('is_finished', false)
                                            ->where('remaining_quantity', '>', 0)
                                            ->orderByRaw('CASE WHEN expired_date IS NULL THEN 1 ELSE 0 END')
                                            ->orderBy('expired_date', 'asc')
                                            ->get(['id', 'quantity', 'remaining_quantity', 'expired_date', 'created_at']);

        return [
            'id'           => $this->id,

            // Stok dalam satuan dasar (gram/ml/pcs)
            'stok'         => $stokDasar,
            'satuan_dasar' => $satuanDasar,

            // Stok dalam satuan besar untuk display (kg/liter)
            'stok_display' => $stokDisplay,
            'satuan'       => $satuan,

            // Contoh: "2.5 kg (2500 gram)"
            'stok_label'   => $konversi > 1
                                ? "{$stokDisplay} {$satuan} ({$stokDasar} {$satuanDasar})"
                                : "{$stokDasar} {$satuanDasar}",

            'stok_minimum'         => $minimumDasar,
            'stok_minimum_display' => $minimumDisplay,
            'stok_minimum_label'   => $konversi > 1
                                        ? "{$minimumDisplay} {$satuan} ({$minimumDasar} {$satuanDasar})"
                                        : "{$minimumDasar} {$satuanDasar}",
            'is_menipis'   => $stokDasar <= $minimumDasar,

            'batch_aktif'  => $batches->map(fn($b) => [
                'id'                 => $b->id,
                'jumlah_awal'        => (float) $b->quantity,
                'sisa'               => (float) $b->remaining_quantity,
                'sisa_display'       => $konversi > 1
                                        ? round($b->remaining_quantity / $konversi, 3) . " {$satuan}"
                                        : $b->remaining_quantity . " {$satuanDasar}",
                'expired_date'       => $b->expired_date?->format('Y-m-d'),
                'tanggal_masuk'      => $b->created_at->format('Y-m-d'),
                'sudah_expired'      => $b->expired_date && $b->expired_date->isPast(),
                'mendekati_expired'  => $b->expired_date && !$b->expired_date->isPast()
                                        && $b->expired_date->diffInDays(now()) <= 3,
            ]),

            
            'batch_terdekat_expired' => $batches->first() ? [
                'sisa'               => (float) $batches->first()->remaining_quantity,
                'expired_date'       => $batches->first()->expired_date?->format('Y-m-d'),
                'tanggal_masuk'      => $batches->first()->created_at->format('Y-m-d'),
            ] : null,

            'is_sudah_expired'       => $batches->first() && $batches->first()->expired_date && $batches->first()->expired_date->isPast(),
            'is_mendekati_expired'   => $batches->first() && $batches->first()->expired_date && !$batches->first()->expired_date->isPast() && $batches->first()->expired_date->diffInDays(now()) <= 3,


            'bahan_master' => $this->whenLoaded('bahanMaster', fn() => [
                'id'                => $this->bahanMaster->id,
                'nama'              => $this->bahanMaster->nama,
                'satuan'            => $this->bahanMaster->satuan,
                'satuan_dasar'      => $this->bahanMaster->satuan_dasar,
                'konversi_ke_dasar' => (float) $this->bahanMaster->konversi_ke_dasar,
                'info_konversi'     => "1 {$this->bahanMaster->satuan} = {$this->bahanMaster->konversi_ke_dasar} {$this->bahanMaster->satuan_dasar}",
                'gambar'            => $this->bahanMaster->gambar
                                        ? asset('storage/' . $this->bahanMaster->gambar)
                                        : null,
            ]),
        ];
    }
}