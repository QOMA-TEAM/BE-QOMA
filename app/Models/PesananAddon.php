<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PesananAddon extends Model
{
    protected $table = 'pesanan_addon';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $fillable = ['id', 'pesanan_detail_id', 'addon_id', 'qty'];

    public function detail() { return $this->belongsTo(PesananDetail::class, 'pesanan_detail_id'); }
    public function addon()  { return $this->belongsTo(Addon::class, 'addon_id'); }
}