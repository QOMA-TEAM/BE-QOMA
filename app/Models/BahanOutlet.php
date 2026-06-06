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
        // ← HAPUS tanggal_masuk dan tanggal_kadaluarsa
    ];

    protected $casts = [
        'stok'         => 'decimal:2',
        'stok_minimum' => 'decimal:2',
    ];

    public function outlet()      { return $this->belongsTo(Outlet::class, 'outlet_id'); }
    public function bahanMaster() { return $this->belongsTo(BahanMaster::class, 'bahan_master_id'); }

    // Ambil batch yang paling dekat expired (untuk display)
    public function batchAktif()
    {
        return $this->hasMany(StockMovement::class, 'bahan_master_id', 'bahan_master_id')
                    ->where('outlet_id', $this->outlet_id)
                    ->where('type', 'in')
                    ->where('is_finished', false)
                    ->where('remaining_quantity', '>', 0)
                    ->orderBy('expired_date', 'asc');
    }

    public function isMenipis(): bool
    {
        return $this->stok <= $this->stok_minimum;
    }
}