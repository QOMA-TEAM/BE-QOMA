<?php
namespace App\Events;

use App\Models\BahanOutletApproval;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ApprovalHargaBahanBaru implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public BahanOutletApproval $approval) {}

    public function broadcastOn(): array
    {
        return [new Channel("owner.{$this->approval->usaha_id}")];
    }

    public function broadcastAs(): string { return 'approval.harga.bahan.baru'; }

    public function broadcastWith(): array
    {
        return [
            'approval_id' => $this->approval->id,
            'outlet'      => $this->approval->outlet->nama_outlet ?? '-',
            'bahan'       => $this->approval->bahanOutlet->bahanMaster->nama ?? '-',
            'harga_lama'  => (float) $this->approval->harga_lama,
            'harga_baru'  => (float) $this->approval->harga_baru,
            'selisih'     => (float) ($this->approval->harga_baru - $this->approval->harga_lama),
            'alasan'      => $this->approval->alasan,
            'pesan'       => "Outlet '{$this->approval->outlet->nama_outlet}' mengajukan perubahan harga bahan baku.",
        ];
    }
}