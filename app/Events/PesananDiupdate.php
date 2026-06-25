<?php

namespace App\Events;

use App\Models\Pesanan;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PesananDiupdate implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Pesanan $pesanan,
        public string  $aksi  // 'konfirmasi', 'bayar', 'cancel', 'edit_item'
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("outlet.{$this->pesanan->outlet_id}"),
            // Pelanggan juga bisa monitor pesanannya
            new Channel("pesanan.{$this->pesanan->id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'pesanan.update';
    }

    public function broadcastWith(): array
    {
        return [
            'pesanan_id'  => $this->pesanan->id,
            'status'      => $this->pesanan->status,
            'aksi'        => $this->aksi,
            'total_harga' => (float) $this->pesanan->total_harga,
            'updated_at'  => now()->format('H:i'),
        ];
    }
}
