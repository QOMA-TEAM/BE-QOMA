<?php
namespace App\Http\Resources\Outlet;
use Illuminate\Http\Resources\Json\JsonResource;

class PesananDetailResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'       => $this->id,
            'menu_id'  => $this->menu_id,
            'nama'     => $this->whenLoaded('menu', fn() => $this->menu->nama, '-'),
            'qty'      => (float) $this->qty,
            'harga'    => (float) $this->harga,
            'subtotal' => (float) ($this->harga * $this->qty),
            'addons'   => $this->whenLoaded('addons', fn() =>
                $this->addons->map(fn($a) => [
                    'id'       => $a->id,
                    'nama'     => $a->addon->nama ?? '-',
                    'qty'      => (float) $a->qty,
                    'harga'    => (float) ($a->addon->harga ?? 0),
                    'subtotal' => (float) (($a->addon->harga ?? 0) * $a->qty),
                ])
            ),
        ];
    }
}
