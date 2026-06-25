<?php
namespace App\Http\Resources\Outlet;
use Illuminate\Http\Resources\Json\JsonResource;

class MenuOutletResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'           => $this->id,
            'menu_id'      => $this->menu_id,
            'harga'        => (float) $this->harga,
            'is_available' => $this->is_available,
            'menu'         => $this->whenLoaded('menu', fn() => [
                'id'        => $this->menu->id,
                'nama'      => $this->menu->nama,
                'keterangan'=> $this->menu->keterangan,
                'gambar' => app(\App\Services\ImageService::class)->url($this->menu->gambar ?? null),
                'kategori'  => $this->menu->kategori->nama ?? '-',
            ]),
        ];
    }
}
