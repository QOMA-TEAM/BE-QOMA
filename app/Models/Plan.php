<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = [
        'id',
        'nama_plan',
        'harga',
        'batas_outlet',
        'durasi_hari',
        'deskripsi',
        'is_lifetime',
        'status',       // ← tambah ini
    ];

    protected $casts = [
        'harga'       => 'decimal:2',
        'is_lifetime' => 'boolean',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
