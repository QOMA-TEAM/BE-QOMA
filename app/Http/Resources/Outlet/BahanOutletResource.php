<?php
namespace App\Http\Resources\Outlet;
use Illuminate\Http\Resources\Json\JsonResource;

class BahanOutletResource extends JsonResource
{
    public function toArray($request): array
    {
        $isMenipis        = $this->stok <= $this->stok_minimum;
        $mendekatiExpired = $this->tanggal_kadaluarsa
            && $this->tanggal_kadaluarsa->isFuture()
            && $this->tanggal_kadaluarsa->diffInDays(now()) <= 3;
        $sudahExpired = $this->tanggal_kadaluarsa
            && $this->tanggal_kadaluarsa->isPast();

        return [
            'id'                  => $this->id,
            'stok'                => (float) $this->stok,
            'stok_minimum'        => (float) $this->stok_minimum,
            'tanggal_masuk'       => $this->tanggal_masuk?->format('Y-m-d'),
            'tanggal_kadaluarsa'  => $this->tanggal_kadaluarsa?->format('Y-m-d'),
            'is_menipis'          => $isMenipis,
            'is_mendekati_expired'=> $mendekatiExpired,
            'is_sudah_expired'    => $sudahExpired,
            'bahan_master'        => $this->whenLoaded('bahanMaster', fn() => [
                'id'            => $this->bahanMaster->id,
                'nama'          => $this->bahanMaster->nama,
                'satuan'        => $this->bahanMaster->satuan,
                'harga_default' => (float) $this->bahanMaster->harga_default,
                'gambar' => $this->bahanMaster->gambar
                                ? asset('storage/' . $this->bahanMaster->gambar)
                                : null,
            ]),
        ];
    }
}

