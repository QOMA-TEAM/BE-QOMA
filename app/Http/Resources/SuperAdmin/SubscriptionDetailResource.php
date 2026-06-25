<?php
namespace App\Http\Resources\SuperAdmin;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionDetailResource extends JsonResource
{
    public function toArray($request): array
    {
        $totalOutlet = \App\Models\Outlet::where('usaha_id', $this->usaha_id)->count();

        return [
            'detail_subscription' => [
                'subscription_id' => $this->id,
                'plan_id'         => $this->plan_id,
                'status'          => $this->status,
                'start_date'      => $this->start_date?->format('Y-m-d'),
                'end_date'        => $this->end_date?->format('Y-m-d'),
                'created_at'      => $this->created_at?->format('Y-m-d H:i'),
                'updated_at'      => $this->updated_at?->format('Y-m-d H:i'),
                'plan'            => $this->whenLoaded('plan', fn() => [
                    'id'          => $this->plan->id,
                    'nama_plan'   => $this->plan->nama_plan,
                    'harga'       => (float) $this->plan->harga,
                    'durasi_hari' => $this->plan->durasi_hari,
                ]),
            ],
            'detail_usaha' => $this->whenLoaded('usaha', fn() => [
                'nama_perusahaan' => $this->usaha->nama_usaha,
                'email'           => $this->usaha->email,
                'alamat'          => $this->usaha->alamat,
                'total_outlet'    => $totalOutlet,
                'owner'           => $this->usaha->owner ? [
                    'nama'     => $this->usaha->owner->nama_lengkap,
                    'username' => $this->usaha->owner->username,
                    'email'    => $this->usaha->owner->email,
                ] : null,
            ]),
        ];
    }
}
