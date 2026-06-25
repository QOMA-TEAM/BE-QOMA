<?php
namespace App\Http\Resources\Shared;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityLogResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'         => $this->id,
            'aktivitas'  => $this->aktivitas,
            'deskripsi'  => $this->deskripsi,
            'metadata'   => $this->metadata,
            'ip_address' => $this->ip_address,
            'created_at' => $this->created_at?->format('Y-m-d H:i'),
            'user'       => $this->whenLoaded('user', fn() => [
                'id'           => $this->user->id,
                'username'     => $this->user->username,
                'nama_lengkap' => $this->user->nama_lengkap,
            ]),
        ];
    }
}
