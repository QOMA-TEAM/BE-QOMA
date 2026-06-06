<?php
namespace App\Services\Owner;

use App\Events\{ApprovalHargaBahanBaru, ApprovalHargaBahanDiproses};
use App\Models\{BahanMaster, BahanOutlet, BahanOutletApproval};
use App\Services\ActivityLogService;
use Illuminate\Support\Str;

class BahanOutletApprovalService
{
    /**
     * Outlet ajukan perubahan harga bahan baku
     * harga_default di bahan_master BELUM berubah sampai approved
     */
    public function ajukanPerubahanHarga(
        BahanOutlet $bahanOutlet,
        float       $hargaBaru,
        string      $alasan,
        string      $outletId,
        string      $usahaId
    ): BahanOutletApproval {

        // Cek ada pending approval untuk bahan ini
        $pendingAda = BahanOutletApproval::where('bahan_outlet_id', $bahanOutlet->id)
                                         ->where('status', 'pending')
                                         ->exists();

        if ($pendingAda) {
            throw new \Exception('Masih ada perubahan harga bahan baku yang menunggu approval. Tunggu sampai diproses.');
        }

        if ($hargaBaru <= 0) {
            throw new \Exception('Harga baru harus lebih dari 0.');
        }

        $hargaLama = $bahanOutlet->bahanMaster->harga_default;

        // Simpan pengajuan — harga_default bahan_master BELUM berubah
        $approval = BahanOutletApproval::create([
            'id'              => Str::uuid(),
            'bahan_outlet_id' => $bahanOutlet->id,
            'outlet_id'       => $outletId,
            'usaha_id'        => $usahaId,
            'harga_lama'      => $hargaLama,
            'harga_baru'      => $hargaBaru,
            'alasan'          => $alasan,
            'status'          => 'pending',
        ]);

        ActivityLogService::log(
            'ajukan_perubahan_harga_bahan',
            "Outlet mengajukan perubahan harga bahan '{$bahanOutlet->bahanMaster->nama}' dari Rp " .
            number_format($hargaLama) . " → Rp " . number_format($hargaBaru),
            [
                'approval_id' => $approval->id,
                'harga_lama'  => $hargaLama,
                'harga_baru'  => $hargaBaru,
                'alasan'      => $alasan,
            ],
            $usahaId,
            $outletId,
        );

        // Broadcast ke owner realtime
        try {
            broadcast(new ApprovalHargaBahanBaru(
                $approval->load(['bahanOutlet.bahanMaster', 'outlet'])
            ))->toOthers();
        } catch (\Exception $e) {
            \Log::warning('Broadcast ApprovalHargaBahanBaru gagal: ' . $e->getMessage());
        }

        // Notif ke owner
        \App\Services\NotificationService::notify(
            \App\Models\Usaha::find($usahaId)?->owner_id ?? '',
            'Permohonan Perubahan Harga Bahan Baku',
            "Outlet '{$approval->outlet->nama_outlet}' mengajukan perubahan harga bahan '{$bahanOutlet->bahanMaster->nama}' " .
            "dari Rp " . number_format($hargaLama) . " → Rp " . number_format($hargaBaru) . ". Alasan: {$alasan}",
            'approval_harga_bahan',
            ['approval_id' => $approval->id],
        );

        return $approval->load(['bahanOutlet.bahanMaster', 'outlet']);
    }

    /**
     * Owner approve — harga_default di bahan_master ikut berubah
     * Semua outlet yang pakai bahan ini akan pakai harga baru
     */
    public function approve(BahanOutletApproval $approval, ?string $catatan = null): BahanOutletApproval
    {
        if ($approval->status !== 'pending') {
            throw new \Exception('Approval ini sudah diproses sebelumnya.');
        }

        // Update harga_default di bahan_master
        // (karena harga bahan baku naik di wilayah outlet tersebut)
        $approval->bahanOutlet->bahanMaster->update([
            'harga_default' => $approval->harga_baru,
        ]);

        $approval->update([
            'status'        => 'approved',
            'catatan_owner' => $catatan,
            'diproses_at'   => now(),
        ]);

        ActivityLogService::log(
            'approve_perubahan_harga_bahan',
            "Owner menyetujui perubahan harga bahan '{$approval->bahanOutlet->bahanMaster->nama}': Rp " .
            number_format($approval->harga_lama) . " → Rp " . number_format($approval->harga_baru),
            ['approval_id' => $approval->id, 'catatan' => $catatan],
            $approval->usaha_id,
            $approval->outlet_id,
        );

        try {
            broadcast(new ApprovalHargaBahanDiproses(
                $approval->load(['bahanOutlet.bahanMaster', 'outlet'])
            ))->toOthers();
        } catch (\Exception $e) {
            \Log::warning('Broadcast ApprovalHargaBahanDiproses gagal: ' . $e->getMessage());
        }

        return $approval->fresh(['bahanOutlet.bahanMaster', 'outlet']);
    }

    /**
     * Owner reject — harga_default tidak berubah
     */
    public function reject(BahanOutletApproval $approval, string $catatan): BahanOutletApproval
    {
        if ($approval->status !== 'pending') {
            throw new \Exception('Approval ini sudah diproses sebelumnya.');
        }

        $approval->update([
            'status'        => 'rejected',
            'catatan_owner' => $catatan,
            'diproses_at'   => now(),
        ]);

        ActivityLogService::log(
            'reject_perubahan_harga_bahan',
            "Owner menolak perubahan harga bahan '{$approval->bahanOutlet->bahanMaster->nama}'. Alasan: {$catatan}",
            ['approval_id' => $approval->id, 'catatan' => $catatan],
            $approval->usaha_id,
            $approval->outlet_id,
        );

        try {
            broadcast(new ApprovalHargaBahanDiproses(
                $approval->load(['bahanOutlet.bahanMaster', 'outlet'])
            ))->toOthers();
        } catch (\Exception $e) {
            \Log::warning('Broadcast ApprovalHargaBahanDiproses gagal: ' . $e->getMessage());
        }

        return $approval->fresh(['bahanOutlet.bahanMaster', 'outlet']);
    }

    /**
     * List untuk owner — semua outlet miliknya
     */
    public function listUntukOwner(string $usahaId, array $filters = [])
    {
        $query = BahanOutletApproval::where('usaha_id', $usahaId)
                                    ->with([
                                        'bahanOutlet.bahanMaster:id,nama,satuan,harga_default',
                                        'outlet:id,nama_outlet',
                                    ])
                                    ->latest();

        if (!empty($filters['status']))    $query->where('status', $filters['status']);
        if (!empty($filters['outlet_id'])) $query->where('outlet_id', $filters['outlet_id']);

        return $query->paginate($filters['per_page'] ?? 15);
    }

    /**
     * List untuk outlet tertentu
     */
    public function listUntukOutlet(string $outletId, array $filters = [])
    {
        $query = BahanOutletApproval::where('outlet_id', $outletId)
                                    ->with(['bahanOutlet.bahanMaster:id,nama,satuan'])
                                    ->latest();

        if (!empty($filters['status'])) $query->where('status', $filters['status']);

        return $query->paginate($filters['per_page'] ?? 15);
    }
}