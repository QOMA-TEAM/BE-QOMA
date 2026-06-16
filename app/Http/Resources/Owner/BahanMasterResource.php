<?php
namespace App\Http\Resources\Owner;
use Illuminate\Http\Resources\Json\JsonResource;

class BahanMasterResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                => $this->id,
            'nama'              => $this->nama,
            'satuan'            => $this->satuan,
            'satuan_dasar'      => $this->satuan_dasar,
            'konversi_ke_dasar' => (float) $this->konversi_ke_dasar,
            'info_konversi'     => "1 {$this->satuan} = {$this->konversi_ke_dasar} {$this->satuan_dasar}",
            'harga_default'     => (float) $this->harga_default,
            'gambar' => app(\App\Services\ImageService::class)->url($this->gambar),
        ];
    }
}