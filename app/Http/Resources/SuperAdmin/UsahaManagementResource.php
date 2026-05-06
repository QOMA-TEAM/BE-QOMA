<?php
namespace App\Http\Resources\SuperAdmin;
use Illuminate\Http\Resources\Json\JsonResource;

class UsahaManagementResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'nama_usaha'     => $this->nama_usaha,
            'email'          => $this->email,
            'alamat'         => $this->alamat,
            'status'         => $this->status,
            'catatan_admin'  => $this->catatan_admin,
            'approved_at'    => $this->approved_at?->format('Y-m-d H:i'),
            'rejected_at'    => $this->rejected_at?->format('Y-m-d H:i'),
            'outlets_count'  => $this->whenCounted('outlets'),
            'created_at'     => $this->created_at?->format('Y-m-d'),
            'owner'          => $this->whenLoaded('owner', fn() => [
                'id'           => $this->owner->id,
                'username'     => $this->owner->username,
                'nama_lengkap' => $this->owner->nama_lengkap,
                'email'        => $this->owner->email,
                'is_active'    => $this->owner->is_active,
            ]),
            'subscription'   => $this->whenLoaded('subscription', fn() =>
                $this->subscription ? [
                    'plan'       => $this->subscription->plan->nama_plan ?? '-',
                    'status'     => $this->subscription->status,
                    'start_date' => $this->subscription->start_date?->format('Y-m-d'),
                    'end_date'   => $this->subscription->end_date?->format('Y-m-d'),
                ] : null
            ),
            'outlets'        => $this->whenLoaded('outlets', fn() =>
                $this->outlets->map(fn($o) => [
                    'id'          => $o->id,
                    'nama_outlet' => $o->nama_outlet,
                    'status_buka' => $o->status_buka,
                ])
            ),
        ];
    }
}