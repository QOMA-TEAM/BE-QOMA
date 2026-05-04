<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class PesananDetail extends Model
{
    protected $table = 'pesanan_detail';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $fillable = ['id', 'pesanan_id', 'menu_id', 'qty', 'harga'];
    protected $casts    = ['harga' => 'decimal:2'];

    public function pesanan() { return $this->belongsTo(Pesanan::class, 'pesanan_id'); }
    public function menu()    { return $this->belongsTo(Menu::class, 'menu_id'); }
    public function addons()  { return $this->hasMany(PesananAddon::class, 'pesanan_detail_id'); }
}