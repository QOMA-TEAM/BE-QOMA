<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PasswordHistory extends Model
{
    protected $table = 'password_histories';
    
    // We are using UUIDs for id.
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'user_id', 'password',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
