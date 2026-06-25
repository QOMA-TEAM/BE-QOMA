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
    ];

    protected function isProcessed(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: fn ($value) => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            set: fn ($value) => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false',
        );
    }

    public function usaha()        { return $this->belongsTo(Usaha::class, 'usaha_id'); }
    public function subscription() { return $this->belongsTo(Subscription::class, 'subscription_id'); }
}
