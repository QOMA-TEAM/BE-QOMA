<?php
namespace App\Events;

use App\Models\Pesanan;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PesananExpired implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Pesanan $pesanan) {}

    public function broadcastOn(): array
    {
        return [
            // Kasir channel — pesanan hilang dari list
            new Channel("outlet.{$this->pesanan->outlet_id}"),
            // Pelanggan channel — tampilkan pesan expired
            new Channel("pesanan.{$this->pesanan->id}"),
        ];
    }

    public function broadcastAs(): string { return 'pesanan.expired'; }

    public function broadcastWith(): array
    {
        return [
            'pesanan_id'  => $this->pesanan->id,
            'status'      => 'expired',
            'pesan'       => 'Pesanan Anda telah kedaluwarsa karena melewati batas waktu 10 menit.',
        ];
    }
}
