<?php
namespace App\Services\Owner;

use App\Events\{ApprovalHargaMenuBaru, ApprovalHargaMenuDiproses};
use App\Models\{MenuOutlet, MenuOutletApproval};
use App\Services\ActivityLogService;
use Illuminate\Support\Str;

class MenuOutletApprovalService
{
    /**
     * Outlet ajukan perubahan harga — harga lama tetap dipakai dulu
     */
    public function ajukanPerubahanHarga(
        MenuOutlet $menuOutlet,
        float      $hargaBaru,
        string     $alasan,
        string     $outletId,
        string     $usahaId
    ): MenuOutletApproval {

        // Cek apakah ada approval pending untuk menu_outlet ini
        $pendingAda = MenuOutletApproval::where('menu_outlet_id', $menuOutlet->id)
                                        ->where('status', 'pending')
                                        ->exists();

        if ($pendingAda) {
            throw new \Exception('Masih ada perubahan harga yang menunggu approval owner. Tunggu sampai diproses.');
        }

        if ($hargaBaru <= 0) {
            throw new \Exception('Harga baru harus lebih dari 0.');
        }

        // Simpan pengajuan — harga menu_outlet BELUM berubah
        $approval = MenuOutletApproval::create([
            'id'            => Str::uuid(),
            'menu_outlet_id'=> $menuOutlet->id,
            'outlet_id'     => $outletId,
            'usaha_id'      => $usahaId,
            'harga_lama'    => $menuOutlet->harga,
            'harga_baru'    => $hargaBaru,
            'alasan'        => $alasan,
            'status'        => 'pending',
        ]);

        ActivityLogService::log(
            'ajukan_perubahan_harga',
            "Outlet mengajukan perubahan harga menu '{$menuOutlet->menu->nama}' dari Rp " .
            number_format($menuOutlet->harga) . " → Rp " . number_format($hargaBaru),
            [
                'approval_id' => $approval->id,
                'harga_lama'  => $menuOutlet->harga,
                'harga_baru'  => $hargaBaru,
                'alasan'      => $alasan,
            ],
            $usahaId,
            $outletId,
        );

        // Broadcast ke owner realtime
        broadcast(new ApprovalHargaMenuBaru(
            $approval->load(['menuOutlet.menu', 'outlet'])
        ))->toOthers();

        // Kirim notifikasi ke owner
        \App\Services\NotificationService::notify(
            \App\Models\Usaha::find($usahaId)?->owner_id ?? '',
            'Permohonan Perubahan Harga Menu',
            "Outlet '{$approval->outlet->nama_outlet}' mengajukan perubahan harga menu '{$menuOutlet->menu->nama}' " .
            "dari Rp " . number_format($menuOutlet->harga) . " → Rp " . number_format($hargaBaru) . ". Alasan: {$alasan}",
            'approval_harga_menu',
            ['approval_id' => $approval->id],
        );

        return $approval->load(['menuOutlet.menu', 'outlet']);
    }

    /**
     * Owner approve perubahan harga → harga baru mulai berlaku
     */
    public function approve(MenuOutletApproval $approval, ?string $catatan = null): MenuOutletApproval
    {
        if ($approval->status !== 'pending') {
            throw new \Exception('Approval ini sudah diproses sebelumnya.');
        }

        // Update harga di menu_outlet — baru berlaku setelah approved
        $approval->menuOutlet->update(['harga' => $approval->harga_baru]);

        $approval->update([
            'status'       => 'approved',
            'catatan_owner'=> $catatan,
            'diproses_at'  => now(),
        ]);

        ActivityLogService::log(
            'approve_perubahan_harga',
            "Owner menyetujui perubahan harga menu '{$approval->menuOutlet->menu->nama}' " .
            "outlet '{$approval->outlet->nama_outlet}': Rp " .
            number_format($approval->harga_lama) . " → Rp " . number_format($approval->harga_baru),
            ['approval_id' => $approval->id, 'catatan' => $catatan],
            $approval->usaha_id,
            $approval->outlet_id,
        );

        // Broadcast ke outlet
        broadcast(new ApprovalHargaMenuDiproses(
            $approval->load(['menuOutlet.menu', 'outlet'])
        ))->toOthers();

        return $approval->fresh(['menuOutlet.menu', 'outlet']);
    }

    /**
     * Owner reject perubahan harga → harga tetap lama
     */
    public function reject(MenuOutletApproval $approval, string $catatan): MenuOutletApproval
    {
        if ($approval->status !== 'pending') {
            throw new \Exception('Approval ini sudah diproses sebelumnya.');
        }

        // Harga menu_outlet TIDAK berubah
        $approval->update([
            'status'        => 'rejected',
            'catatan_owner' => $catatan,
            'diproses_at'   => now(),
        ]);

        ActivityLogService::log(
            'reject_perubahan_harga',
            "Owner menolak perubahan harga menu '{$approval->menuOutlet->menu->nama}' " .
            "outlet '{$approval->outlet->nama_outlet}'. Alasan: {$catatan}",
            ['approval_id' => $approval->id, 'catatan' => $catatan],
            $approval->usaha_id,
            $approval->outlet_id,
        );

        broadcast(new ApprovalHargaMenuDiproses(
            $approval->load(['menuOutlet.menu', 'outlet'])
        ))->toOthers();

        return $approval->fresh(['menuOutlet.menu', 'outlet']);
    }

    /**
     * List semua approval untuk owner (semua outlet miliknya)
     */
    public function listUntukOwner(string $usahaId, array $filters = [])
    {
        $query = MenuOutletApproval::where('usaha_id', $usahaId)
                                   ->with(['menuOutlet.menu:id,nama', 'outlet:id,nama_outlet'])
                                   ->latest();

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['outlet_id'])) {
            $query->where('outlet_id', $filters['outlet_id']);
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    /**
     * List approval untuk outlet tertentu
     */
    public function listUntukOutlet(string $outletId, array $filters = [])
    {
        $query = MenuOutletApproval::where('outlet_id', $outletId)
                                   ->with(['menuOutlet.menu:id,nama'])
                                   ->latest();

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }
}
