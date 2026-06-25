<?php
namespace App\Http\Resources\Owner;
use Illuminate\Http\Resources\Json\JsonResource;

class KategoriMenuResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'          => $this->id,
            'nama'        => $this->nama,
            'menus_count' => $this->whenCounted('menus'),
        ];
    }
}
