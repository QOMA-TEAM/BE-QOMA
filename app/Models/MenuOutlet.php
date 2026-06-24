<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class MenuOutlet extends Model
{
    protected $table = 'menu_outlet';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $fillable = ['id', 'menu_id', 'outlet_id', 'harga', 'is_available'];
    protected $casts    = ['harga' => 'decimal:2'];

    protected function isAvailable(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: fn ($value) => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            set: fn ($value) => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false',
        );
    }

    public function menu()   { return $this->belongsTo(Menu::class, 'menu_id'); }
    public function outlet() { return $this->belongsTo(Outlet::class, 'outlet_id'); }
}
