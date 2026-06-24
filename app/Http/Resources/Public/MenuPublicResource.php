<?php
namespace App\Http\Resources\Public;
use Illuminate\Http\Resources\Json\JsonResource;

class MenuPublicResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'           => $this->id,
            'nama'         => $this->nama,
            'kategori'     => $this->whenLoaded('kategori', fn() => $this->kategori->nama, '-'),
            'kategori_id'  => $this->kategori_id,
            'harga'        => (float) ($this->harga_outlet ?? $this->harga_default),
            'keterangan'   => $this->keterangan,
            'is_available' => $this->is_available ?? true,
            'gambar' => app(\App\Services\ImageService::class)->url($this->gambar),
            'bahan_baku'   => $this->whenLoaded('bahanMasters', fn() =>
                $this->bahanMasters->map(fn($b) => [
                    'nama'   => $b->nama,
                    'satuan' => $b->satuan,
                ])
            ),
            'addons'       => $this->when(
                isset($this->addons_tersedia),
                fn() => collect($this->addons_tersedia)->map(fn($a) => [
                    'id'    => $a['id'],
                    'nama'  => $a['nama'],
                    'harga' => (float) $a['harga'],
                ])
            ),
        ];
    }
}
