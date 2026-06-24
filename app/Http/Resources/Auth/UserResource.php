<?php
namespace App\Http\Resources\Auth;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'           => $this->id,
            'username'     => $this->username,
            'nama_lengkap' => $this->nama_lengkap,
            'email'        => $this->email,
            'role'         => $this->whenLoaded('role', fn() => $this->role->name),
            'usaha_id'     => $this->usaha_id,
            'outlet_id'    => $this->outlet_id,
            'is_active'    => $this->is_active,
        ];
    }
}
