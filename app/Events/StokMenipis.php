<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StokMenipis implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $outletId,
        public array  $alerts  // data alert dari BahanOutletService::getAlerts()
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("outlet.{$this->outletId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'stok.menipis';
    }

    public function broadcastWith(): array
    {
        return [
            'outlet_id'    => $this->outletId,
            'total_alert'  => $this->alerts['total_alert'],
            'stok_menipis' => $this->alerts['stok_menipis'],
        ];
    }
}