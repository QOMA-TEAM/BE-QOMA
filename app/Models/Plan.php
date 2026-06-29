<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plan extends Model
{
    use SoftDeletes;
    protected $table = 'plans';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'nama_plan',
        'harga',
        'batas_outlet',
        'durasi_hari',
        'is_lifetime',
        'deskripsi',
        'status'
    ];

    protected $casts = [
        'is_lifetime' => 'boolean',
    ];

    public function subscriptions()
    {
        return $this->hasMany(
            Subscription::class,
            'plan_id',
            'id'
        );
    }
}
