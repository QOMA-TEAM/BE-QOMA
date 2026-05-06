<?php
namespace App\Http\Resources\Owner;
use Illuminate\Http\Resources\Json\JsonResource;

class UsahaResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'           => $this->id,
            'nama_usaha'   => $this->nama_usaha,
            'email'        => $this->email,
            'alamat'       => $this->alamat,
            'status'       => $this->status,
            'approved_at'  => $this->approved_at?->format('Y-m-d H:i'),
            'outlets_count'=> $this->whenCounted('outlets'),
            'owner'        => $this->whenLoaded('owner', fn() => [
                'id'           => $this->owner->id,
                'nama_lengkap' => $this->owner->nama_lengkap,
                'email'        => $this->owner->email,
            ]),
            'subscription' => $this->whenLoaded('subscription', fn() =>
                new SubscriptionResource($this->subscription)
            ),
            'outlets'      => $this->whenLoaded('outlets', fn() =>
                OutletResource::collection($this->outlets)
            ),
        ];
    }
}