<?php

namespace App\Http\Resources\Outlet;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockOpnameSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'outlet_id'   => $this->outlet_id,
            'tanggal'     => $this->tanggal instanceof \Carbon\Carbon ? $this->tanggal->format('Y-m-d') : $this->tanggal,
            'status'      => $this->status,
            'closed_at'   => $this->closed_at,
            'created_at'  => $this->created_at,
            
            // For history list
            'total_item'  => $this->total_item ?? null,
            'total_draft' => $this->total_draft ?? null,
            'total_final' => $this->total_final ?? null,

            // When loaded with items
            'items'       => $this->relationLoaded('items') 
                                ? StockOpnameResource::collection($this->items) 
                                : [],
        ];
    }
}