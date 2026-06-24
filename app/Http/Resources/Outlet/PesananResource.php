<?php
namespace App\Http\Resources\Outlet;
use Illuminate\Http\Resources\Json\JsonResource;

class PesananResource extends JsonResource
{
    public function toArray($request): array
    {
        $statusLabel = match($this->status) {
            'pending'   => 'Menunggu konfirmasi',
            'confirmed' => 'Dikonfirmasi',
            'paid'      => 'Lunas',
            'cancelled' => 'Dibatalkan',
            default     => $this->status,
        };

        return [
            'id'             => $this->id,
            'nomor_meja'     => $this->whenLoaded('meja', fn() => $this->meja?->nomor_meja ?? '-', '-'),
            'nama_pelanggan' => $this->nama_pelanggan,
            'no_telp'        => $this->no_telp,
            'total_harga'    => (float) $this->total_harga,
            'status'         => $this->status,
            'tipe_pesanan'   => $this->tipe_pesanan,
            'status_label'   => $statusLabel,
            'created_at'     => $this->created_at?->format('Y-m-d H:i'),
            'items'          => $this->whenLoaded('details', fn() =>
                PesananDetailResource::collection($this->details)
            ),
            'pembayaran'     => $this->whenLoaded('pembayaran', fn() =>
                $this->pembayaran ? [
                    'metode'       => $this->pembayaran->metode,
                    'jumlah_bayar' => (float) $this->pembayaran->jumlah_bayar,
                    'paid_at'      => $this->pembayaran->psid_at?->format('Y-m-d H:i'),
                ] : null
            ),
        ];
    }
}
