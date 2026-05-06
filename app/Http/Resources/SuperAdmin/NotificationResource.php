<?php
namespace App\Http\Resources\SuperAdmin;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'         => $this->id,
            'title'      => $this->title,
            'message'    => $this->message,
            'type'       => $this->type,
            'is_read'    => $this->is_read,
            'data'       => $this->data,
            'created_at' => $this->created_at?->format('Y-m-d H:i'),
        ];
    }
}