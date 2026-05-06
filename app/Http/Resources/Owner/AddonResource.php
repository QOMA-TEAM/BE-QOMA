<?php
namespace App\Http\Resources\Owner;
use Illuminate\Http\Resources\Json\JsonResource;

class AddonResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'    => $this->id,
            'nama'  => $this->nama,
            'harga' => (float) $this->harga,
        ];
    }
}