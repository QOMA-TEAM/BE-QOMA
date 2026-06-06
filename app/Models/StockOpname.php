<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class StockOpname extends Model
{
    protected $table = 'stock_opname';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'outlet_id', 'bahan_master_id',
        'tipe', 'jumlah', 'foto_bukti', 'keterangan',
        'status',
    ];

    protected $casts = ['jumlah' => 'decimal:2'];

    public function outlet()      { return $this->belongsTo(Outlet::class, 'outlet_id'); }
    public function bahanMaster() { return $this->belongsTo(BahanMaster::class, 'bahan_master_id'); }

    public function isDraft(): bool  { return $this->status === 'draft'; }
    public function isFinal(): bool  { return $this->status === 'final'; }
}