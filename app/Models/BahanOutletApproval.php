<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class BahanOutletApproval extends Model
{
    protected $table = 'bahan_outlet_approval';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'bahan_outlet_id', 'outlet_id', 'usaha_id',
        'harga_lama', 'harga_baru', 'alasan',
        'status', 'catatan_owner', 'diproses_at',
    ];

    protected $casts = [
        'harga_lama'  => 'decimal:2',
        'harga_baru'  => 'decimal:2',
        'diproses_at' => 'datetime',
    ];

    public function bahanOutlet() { return $this->belongsTo(BahanOutlet::class, 'bahan_outlet_id'); }
    public function outlet()      { return $this->belongsTo(Outlet::class, 'outlet_id'); }
    public function usaha()       { return $this->belongsTo(Usaha::class, 'usaha_id'); }
}
