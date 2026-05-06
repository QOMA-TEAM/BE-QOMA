<?php

namespace App\Events;

use App\Models\Pesanan;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PesananBaru implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Pesanan $pesanan) {}

    /**
     * Channel: outlet.{outlet_id}
     * Kasir subscribe ke channel outlet mereka sendiri
     */
    public function broadcastOn(): array
    {
        return [
            new Channel("outlet.{$this->pesanan->outlet_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'pesanan.baru';
    }

    public function broadcastWith(): array
    {
        return [
            'pesanan_id'     => $this->pesanan->id,
            'nomor_meja'     => $this->pesanan->meja->nomor_meja ?? '-',
            'nama_pelanggan' => $this->pesanan->nama_pelanggan,
            'no_telp'        => $this->pesanan->no_telp,
            'total_harga'    => (float) $this->pesanan->total_harga,
            'status'         => $this->pesanan->status,
            'created_at'     => $this->pesanan->created_at->format('H:i'),
            'items_count'    => $this->pesanan->details()->count(),
        ];
    }
}