<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class MenuOutletApproval extends Model
{
    protected $table = 'menu_outlet_approval';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'menu_outlet_id', 'outlet_id', 'usaha_id',
        'harga_lama', 'harga_baru', 'alasan',
        'status', 'catatan_owner', 'diproses_at',
    ];

    protected $casts = [
        'harga_lama'   => 'decimal:2',
        'harga_baru'   => 'decimal:2',
        'diproses_at'  => 'datetime',
    ];

    public function menuOutlet() { return $this->belongsTo(MenuOutlet::class, 'menu_outlet_id'); }
    public function outlet()     { return $this->belongsTo(Outlet::class, 'outlet_id'); }
    public function usaha()      { return $this->belongsTo(Usaha::class, 'usaha_id'); }
}
