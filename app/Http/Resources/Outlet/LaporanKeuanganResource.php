<?php
namespace App\Http\Resources\Outlet;
use Illuminate\Http\Resources\Json\JsonResource;

class LaporanKeuanganResource extends JsonResource
{
    public function toArray($request): array
    {
        $keuntungan = (float) $this->total_keuntungan;

        return [
            'periode'           => $this->periode,
            'tipe_periode'      => $this->tipe_periode,
            'total_pendapatan'  => (float) $this->total_pendapatan,
            'total_pengeluaran' => (float) $this->total_pengeluaran,
            'total_kerugian'    => (float) $this->total_kerugian,
            'total_keuntungan'  => $keuntungan,
            'status'            => $keuntungan >= 0 ? 'untung' : 'rugi',
        ];
    }
}
