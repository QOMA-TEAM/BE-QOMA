<?php
namespace App\Http\Resources\Owner;
use Illuminate\Http\Resources\Json\JsonResource;

class BahanMasterResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'            => $this->id,
            'nama'          => $this->nama,
            'satuan'        => $this->satuan,
            'harga_default' => (float) $this->harga_default,
            'gambar'        => $this->gambar
                                ? asset('storage/' . $this->gambar)
                                : null,
        ];
    }
}