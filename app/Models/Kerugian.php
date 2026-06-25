<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Kerugian extends Model
{
    protected $table = 'kerugian';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'outlet_id', 'total_rugi', 'tanggal',
    ];

    protected $casts = [
        'total_rugi' => 'decimal:2',
        'tanggal'    => 'date',
    ];

    public function outlet() { return $this->belongsTo(Outlet::class, 'outlet_id'); }
}
