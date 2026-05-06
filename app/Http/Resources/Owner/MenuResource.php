<?php
namespace App\Http\Resources\Owner;
use Illuminate\Http\Resources\Json\JsonResource;

class MenuResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'nama'           => $this->nama,
            'harga_default'  => (float) $this->harga_default,
            'keterangan'     => $this->keterangan,
            'is_active'      => $this->is_active,
            'gambar'         => $this->gambar
                                    ? asset('storage/' . $this->gambar)
                                    : null,
            'kategori'       => $this->whenLoaded('kategori', fn() => [
                'id'   => $this->kategori->id,
                'nama' => $this->kategori->nama,
            ]),
            'bahan_baku'     => $this->whenLoaded('bahanMasters', fn() =>
                $this->bahanMasters->map(fn($b) => [
                    'id'           => $b->id,
                    'nama'         => $b->nama,
                    'satuan'       => $b->satuan,
                    'jumlah_pakai' => (float) $b->pivot->jumlah_pakai,
                ])
            ),
            'outlet_harga'   => $this->whenLoaded('menuOutlets', fn() =>
                $this->menuOutlets->map(fn($mo) => [
                    'outlet_id'    => $mo->outlet_id,
                    'nama_outlet'  => $mo->outlet->nama_outlet ?? '-',
                    'harga'        => (float) $mo->harga,
                    'is_available' => $mo->is_available,
                ])
            ),
        ];
    }
}