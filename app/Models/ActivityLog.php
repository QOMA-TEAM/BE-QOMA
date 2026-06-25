<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $table = 'activity_logs';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $fillable = ['id', 'user_id', 'usaha_id', 'outlet_id', 'aktivitas', 'deskripsi', 'metadata', 'ip_address'];
    protected $casts    = ['metadata' => 'array'];

    public function user() { return $this->belongsTo(User::class, 'user_id'); }
}
