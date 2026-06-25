<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockOpnameSession extends Model
{
    protected $table = 'stock_opname_sessions';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'outlet_id', 'tanggal', 'status', 'closed_at',
    ];

    protected $casts = [
        'tanggal'   => 'date',
        'closed_at' => 'datetime',
    ];

    public function outlet() { return $this->belongsTo(Outlet::class, 'outlet_id'); }

    public function items()
    {
        return $this->hasMany(StockOpname::class, 'session_id');
    }

    public function itemsDraft()
    {
        return $this->hasMany(StockOpname::class, 'session_id')->where('status', 'draft');
    }

    public function itemsFinal()
    {
        return $this->hasMany(StockOpname::class, 'session_id')->where('status', 'final');
    }

    public function isOpen(): bool   { return $this->status === 'open'; }
    public function isClosed(): bool { return $this->status === 'closed'; }

    public function totalKerugian(): float
    {
        return (float) $this->items()
            ->where('status', 'final')
            ->with('bahanMaster')
            ->get()
            ->sum(fn($item) => $item->jumlah * ($item->bahanMaster->harga_default ?? 0));
    }
}
