<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotifikasiSuperAdmin implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $tipe,    // 'new_owner', 'new_subscription', 'upgrade_plan'
        public string $judul,
        public string $pesan,
        public array  $data = []
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('super-admin'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'notifikasi.baru';
    }

    public function broadcastWith(): array
    {
        return [
            'tipe'  => $this->tipe,
            'judul' => $this->judul,
            'pesan' => $this->pesan,
            'data'  => $this->data,
            'waktu' => now()->format('H:i d/m/Y'),
        ];
    }
}