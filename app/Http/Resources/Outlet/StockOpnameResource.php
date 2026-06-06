<?php
namespace App\Http\Resources\Outlet;
use Illuminate\Http\Resources\Json\JsonResource;

class StockOpnameResource extends JsonResource
{
    // app/Http/Resources/Outlet/StockOpnameResource.php
    public function toArray($request): array
    {
        return [
            'id'          => $this->id,
            'tipe'        => $this->tipe,
            'jumlah'      => (float) $this->jumlah,
            'keterangan'  => $this->keterangan,
            'status'      => $this->status,               
            'is_draft'    => $this->isDraft(),             
            'is_final'    => $this->isFinal(),             
            'foto_bukti'  => $this->foto_bukti
                                ? asset('storage/' . $this->foto_bukti)
                                : null,
            'created_at'  => $this->created_at?->format('Y-m-d H:i'),
            'bahan_master'=> $this->whenLoaded('bahanMaster', fn() => [
                'id'     => $this->bahanMaster->id,
                'nama'   => $this->bahanMaster->nama,
                'satuan' => $this->bahanMaster->satuan,
            ]),
        ];
    }
}