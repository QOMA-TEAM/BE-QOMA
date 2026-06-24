<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Pengeluaran extends Model
{
    protected $table = 'pengeluaran';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'outlet_id', 'bahan_master_id',
        'sumber', 'total', 'tanggal',
    ];

    protected $casts = [
        'total'   => 'decimal:2',
        'tanggal' => 'date',
    ];

    public function outlet()      { return $this->belongsTo(Outlet::class, 'outlet_id'); }
    public function bahanMaster() { return $this->belongsTo(BahanMaster::class, 'bahan_master_id'); }
}
