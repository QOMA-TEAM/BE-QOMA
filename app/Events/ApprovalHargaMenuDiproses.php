<?php
namespace App\Events;

use App\Models\MenuOutletApproval;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ApprovalHargaMenuDiproses implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public MenuOutletApproval $approval) {}

    public function broadcastOn(): array
    {
        return [
            // Outlet channel — dapat notifikasi hasil approval
            new Channel("outlet.{$this->approval->outlet_id}"),
        ];
    }

    public function broadcastAs(): string { return 'approval.harga.diproses'; }

    public function broadcastWith(): array
    {
        $statusLabel = $this->approval->status === 'approved' ? 'disetujui' : 'ditolak';

        return [
            'approval_id'    => $this->approval->id,
            'status'         => $this->approval->status,
            'menu'           => $this->approval->menuOutlet->menu->nama ?? '-',
            'harga_lama'     => (float) $this->approval->harga_lama,
            'harga_baru'     => (float) $this->approval->harga_baru,
            'catatan_owner'  => $this->approval->catatan_owner,
            'pesan'          => "Perubahan harga menu {$statusLabel} oleh owner.",
        ];
    }
}
