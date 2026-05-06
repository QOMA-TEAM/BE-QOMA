<?php
namespace App\Http\Resources\Owner;
use Illuminate\Http\Resources\Json\JsonResource;

class OutletResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'          => $this->id,
            'usaha_id'    => $this->usaha_id,
            'nama_outlet' => $this->nama_outlet,
            'alamat'      => $this->alamat,
            'status_buka' => $this->status_buka,
            'mejas_count' => $this->whenCounted('mejas'),
            'users'       => $this->whenLoaded('users', fn() =>
                $this->users->map(fn($u) => [
                    'id'        => $u->id,
                    'username'  => $u->username,
                    'is_active' => $u->is_active,
                ])
            ),
        ];
    }
}