<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $table = 'stock_movements';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'outlet_id', 'bahan_master_id',
        'type', 'quantity', 'remaining_quantity',
        'expired_date', 'is_finished',
        'reference_id', 'note',
    ];

    protected $casts = [
        'quantity'           => 'decimal:2',
        'remaining_quantity' => 'decimal:2',
        'expired_date'       => 'date',
        'is_finished'        => 'boolean',
    ];

    public function outlet()      { return $this->belongsTo(Outlet::class, 'outlet_id'); }
    public function bahanMaster() { return $this->belongsTo(BahanMaster::class, 'bahan_master_id'); }
}