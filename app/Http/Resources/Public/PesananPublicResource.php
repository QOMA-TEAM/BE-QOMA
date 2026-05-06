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
            default     => $this->status,
        };

        return [
            'pesanan_id'     => $this->id,
            'nomor_meja'     => $this->whenLoaded('meja', fn() => $this->meja->nomor_meja, '-'),
            'nama_pelanggan' => $this->nama_pelanggan,
            'no_telp'        => $this->no_telp,
            'status'         => $this->status,
            'status_label'   => $statusLabel,
            'total_harga'    => (float) $this->total_harga,
            'created_at'     => $this->created_at?->format('Y-m-d H:i'),
            'items'          => $this->whenLoaded('details', fn() =>
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