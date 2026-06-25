<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Meja extends Model
{
    use SoftDeletes;
    protected $table = 'meja';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $fillable = ['id', 'outlet_id', 'nomor_meja', 'qr_code'];

    public function outlet()   { return $this->belongsTo(Outlet::class, 'outlet_id'); }
    public function pesanans() { return $this->hasMany(Pesanan::class, 'meja_id'); }
}
