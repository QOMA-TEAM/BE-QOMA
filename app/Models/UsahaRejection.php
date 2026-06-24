<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsahaRejection extends Model
{
    protected $table = 'usaha_rejection';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['id', 'usaha_id', 'rejected_by', 'alasan'];

    public function usaha()
    {
        return $this->belongsTo(Usaha::class, 'usaha_id');
    }

    public function rejectedBy()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }
}
