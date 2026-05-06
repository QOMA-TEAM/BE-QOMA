<?php
namespace App\Http\Resources\Outlet;
use Illuminate\Http\Resources\Json\JsonResource;

class MejaResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'         => $this->id,
            'nomor_meja' => $this->nomor_meja,
            'qr_code'    => $this->qr_code,
        ];
    }
}