<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Outlet extends Model
{
    use SoftDeletes;
    protected $table = 'outlet';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['id', 'usaha_id', 'nama_outlet', 'alamat', 'status_buka', 'email', 
                            'gambar_icon', 'gambar_header',];
    protected $casts    = [];

    protected function statusBuka(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: fn ($value) => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            set: fn ($value) => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false',
        );
    }
    public function usaha()       { return $this->belongsTo(Usaha::class, 'usaha_id'); }
    public function users()       { return $this->hasMany(User::class, 'outlet_id'); }
    public function mejas()       { return $this->hasMany(Meja::class, 'outlet_id'); }
    public function menuOutlets() { return $this->hasMany(MenuOutlet::class, 'outlet_id'); }
    public function bahanOutlets(){ return $this->hasMany(BahanOutlet::class, 'outlet_id'); }
    public function pesanans()    { return $this->hasMany(Pesanan::class, 'outlet_id'); }
}
