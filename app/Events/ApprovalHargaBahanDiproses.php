<?php
namespace App\Events;

use App\Models\BahanOutletApproval;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ApprovalHargaBahanDiproses implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public BahanOutletApproval $approval) {}

    public function broadcastOn(): array
    {
        return [new Channel("outlet.{$this->approval->outlet_id}")];
    }

    public function broadcastAs(): string { return 'approval.harga.bahan.diproses'; }

    public function broadcastWith(): array
    {
        $statusLabel = $this->approval->status === 'approved' ? 'disetujui' : 'ditolak';

        return [
            'approval_id'   => $this->approval->id,
            'status'        => $this->approval->status,
            'bahan'         => $this->approval->bahanOutlet->bahanMaster->nama ?? '-',
            'harga_lama'    => (float) $this->approval->harga_lama,
            'harga_baru'    => (float) $this->approval->harga_baru,
            'catatan_owner' => $this->approval->catatan_owner,
            'pesan'         => "Perubahan harga bahan baku {$statusLabel} oleh owner.",
        ];
    }
}