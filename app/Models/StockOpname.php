<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class StockOpname extends Model
{
    protected $table = 'stock_opname';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'outlet_id', 'session_id', 'bahan_master_id',
        'tipe', 'jumlah', 'foto_bukti', 'keterangan',
        'status', 'finalized_at',
    ];

    protected $casts = [
        'jumlah'       => 'decimal:2',
        'finalized_at' => 'datetime',
    ];

    public function outlet()      { return $this->belongsTo(Outlet::class, 'outlet_id'); }
    public function session()     { return $this->belongsTo(StockOpnameSession::class, 'session_id'); }
    public function bahanMaster() { return $this->belongsTo(BahanMaster::class, 'bahan_master_id'); }

    public function isDraft(): bool  { return $this->status === 'draft'; }
    public function isFinal(): bool  { return $this->status === 'final'; }

    public function toArray()
    {
        $array = parent::toArray();
        if (!empty($array['foto_bukti']) && !str_starts_with($array['foto_bukti'], 'http')) {
            $array['foto_bukti'] = app(\App\Services\ImageService::class)->url($array['foto_bukti']);
        }
        return $array;
    }
}