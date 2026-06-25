<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MenuBahan extends Model
{
    protected $table = 'menu_bahan';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = ['id','menu_id','bahan_master_id','jumlah_pakai'];

    protected static function boot() {
        parent::boot();
        static::creating(fn($m) => $m->id = $m->id ?: Str::uuid());
    }

    public function menu()        { return $this->belongsTo(Menu::class, 'menu_id'); }
    public function bahanMaster() { return $this->belongsTo(BahanMaster::class, 'bahan_master_id'); }
}
