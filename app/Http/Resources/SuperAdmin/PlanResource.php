<?php
namespace App\Http\Resources\SuperAdmin;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'               => $this->id,
            'nama_plan'        => $this->nama_plan,
            'harga'            => (float) $this->harga,
            'batas_outlet'     => $this->batas_outlet === -1 ? 'Unlimited' : $this->batas_outlet,
            'durasi_hari'      => $this->durasi_hari,
            'deskripsi'        => $this->deskripsi,
            'subscriptions_count' => $this->whenCounted('subscriptions'),
        ];
    }
}