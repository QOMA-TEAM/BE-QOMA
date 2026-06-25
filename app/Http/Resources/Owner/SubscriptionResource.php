<?php
namespace App\Http\Resources\Owner;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    public function toArray($request): array
    {
        $isLifetime = $this->plan->is_lifetime ?? false;

        return [
            'id'          => $this->id,
            'status'      => $this->status,
            'start_date'  => $this->start_date?->format('Y-m-d'),
            'end_date'    => $isLifetime ? null : $this->end_date?->format('Y-m-d'),
            'is_lifetime' => $isLifetime,
            'sisa_hari'   => $isLifetime
                                ? null
                                : ($this->end_date
                                    ? max(0, (int) now()->diffInDays($this->end_date, false))
                                    : null),
            'plan' => $this->whenLoaded('plan', fn() => [
                'id'           => $this->plan->id,
                'nama_plan'    => $this->plan->nama_plan,
                'harga'        => (float) $this->plan->harga,
                'batas_outlet' => $this->plan->batas_outlet === -1
                                    ? 'Unlimited'
                                    : $this->plan->batas_outlet,
                'durasi_hari'  => $isLifetime ? 'Selamanya' : $this->plan->durasi_hari,
                'is_lifetime'  => $isLifetime,
                'deskripsi'    => $this->plan->deskripsi,
            ]),
        ];
    }
}
