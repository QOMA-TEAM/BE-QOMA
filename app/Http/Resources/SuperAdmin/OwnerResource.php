<?php
namespace App\Http\Resources\SuperAdmin;
use Illuminate\Http\Resources\Json\JsonResource;

class OwnerResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'           => $this->id,
            'username'     => $this->username,
            'nama_lengkap' => $this->nama_lengkap,
            'email'        => $this->email,
            'is_active'    => $this->is_active,
            'created_at'   => $this->created_at?->format('Y-m-d'),
            'usaha'        => $this->whenLoaded('usaha', fn() => [
                'id'         => $this->usaha->id,
                'nama_usaha' => $this->usaha->nama_usaha,
                'status'     => $this->usaha->status,
            ]),
        ];
    }
}
