<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $table = 'plans';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'nama_plan',
        'harga',
        'batas_outlet',
        'durasi_hari',
        'deskripsi',
        'status'
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