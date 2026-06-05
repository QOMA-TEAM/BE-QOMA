<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class OutletDeactivationQueue extends Model
{
    protected $table = 'outlet_deactivation_queue';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'usaha_id', 'subscription_id',
        'jumlah_harus_nonaktif', 'deadline', 'is_processed',
    ];

    protected $casts = [
        'deadline'     => 'datetime',
        'is_processed' => 'boolean',
    ];

    public function usaha()        { return $this->belongsTo(Usaha::class, 'usaha_id'); }
    public function subscription() { return $this->belongsTo(Subscription::class, 'subscription_id'); }
}