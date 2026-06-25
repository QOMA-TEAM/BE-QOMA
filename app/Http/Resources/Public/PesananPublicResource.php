<?php
namespace App\Http\Resources\Public;
use Illuminate\Http\Resources\Json\JsonResource;

class PesananPublicResource extends JsonResource
{
    public function toArray($request): array
    {
        $statusLabel = match($this->status) {
            'pending'   => 'Menunggu konfirmasi kasir',
            'confirmed' => 'Dikonfirmasi — silakan lakukan pembayaran ke kasir',
            'paid'      => 'Lunas ✓',
            'cancelled' => 'Dibatalkan',
            'expired'   => 'Pesanan kedaluwarsa',
            default     => $this->status,
        };

        // Timer HANYA aktif saat pending
        // Cancelled/expired/confirmed/paid → timer berhenti
        $timerAktif = $this->status === 'pending';
        $sisaDetik  = ($timerAktif && $this->expired_at)
                        ? max(0, now()->diffInSeconds($this->expired_at, false))
                        : null;

        return [
            'pesanan_id'       => $this->id,
            'nomor_meja'       => $this->whenLoaded('meja', fn() => $this->meja->nomor_meja, '-'),
            'nama_pelanggan'   => $this->nama_pelanggan,
            'no_telp'          => $this->no_telp,
            'status'           => $this->status,
            'status_label'     => $statusLabel,
            'tipe_pesanan'     => $this->tipe_pesanan,
            'total_harga'      => (float) $this->total_harga,
            'created_at'       => $this->created_at?->format('Y-m-d H:i'),
            'expired_at'       => $this->expired_at?->format('Y-m-d H:i:s'),
            'sisa_waktu_detik' => $sisaDetik,
            'timer_aktif'      => $timerAktif,
            'is_cancelled'     => $this->status === 'cancelled',
            'is_expired'       => $this->status === 'expired',
            'items'            => $this->whenLoaded('details', fn() =>
                $this->details->map(fn($d) => [
                    'nama'    => $d->menu->nama ?? '-',
                    'qty'     => $d->qty,
                    'harga'   => (float) $d->harga,
                    'subtotal'=> (float) ($d->harga * $d->qty),
                    'addons'  => $d->addons->map(fn($a) => [
                        'nama'    => $a->addon->nama ?? '-',
                        'qty'     => $a->qty,
                        'subtotal'=> (float) (($a->addon->harga ?? 0) * $a->qty),
                    ]),
                ])
            ),
        ];
    }
}
