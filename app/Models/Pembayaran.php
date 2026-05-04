<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pembayaran extends Model
{
    protected $table = 'pembayaran';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $fillable = ['id', 'pesanan_id', 'metode', 'jumlah_bayar', 'status', 'psid_at'];
    protected $casts    = [
        'jumlah_bayar' => 'decimal:2',
        'psid_at'      => 'datetime',
    ];

    public function pesanan() { return $this->belongsTo(Pesanan::class, 'pesanan_id'); }
}