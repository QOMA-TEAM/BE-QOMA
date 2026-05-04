<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class BahanOutlet extends Model
{
    protected $table = 'bahan_outlet';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $fillable = [
        'id', 'outlet_id', 'bahan_master_id',
        'stok', 'stok_minimum',
        'tanggal_masuk', 'tanggal_kadaluarsa',
    ];
    protected $casts = [
        'stok'               => 'decimal:2',
        'stok_minimum'       => 'decimal:2',
        'tanggal_masuk'      => 'date',
        'tanggal_kadaluarsa' => 'date',
    ];

    public function outlet()      { return $this->belongsTo(Outlet::class, 'outlet_id'); }
    public function bahanMaster() { return $this->belongsTo(BahanMaster::class, 'bahan_master_id'); }

    // Helper: apakah stok menipis
    public function isMenipis(): bool
    {
        return $this->stok <= $this->stok_minimum;
    }

    // Helper: apakah mendekati expired (dalam 3 hari)
    public function isMendekatiExpired(): bool
    {
        if (!$this->tanggal_kadaluarsa) return false;
        return $this->tanggal_kadaluarsa->diffInDays(now()) <= 3
               && $this->tanggal_kadaluarsa->isFuture();
    }
}