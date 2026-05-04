<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Pesanan extends Model
{
    protected $table = 'pesanan';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $fillable = [
        'id', 'outlet_id', 'meja_id',
        'nama_pelanggan', 'no_telp',
        'total_harga', 'status',
    ];
    protected $casts = ['total_harga' => 'decimal:2'];

    public function outlet()  { return $this->belongsTo(Outlet::class, 'outlet_id'); }
    public function meja()    { return $this->belongsTo(Meja::class, 'meja_id'); }
    public function details() { return $this->hasMany(PesananDetail::class, 'pesanan_id'); }
    public function pembayaran() { return $this->hasOne(Pembayaran::class, 'pesanan_id'); }
}