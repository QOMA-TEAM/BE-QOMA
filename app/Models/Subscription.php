<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $table = 'subscriptions';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'usaha_id', 'plan_id',
        'start_date', 'end_date',
        'status', 'tipe', 'grace_period_end',
    ];

    protected $casts = [
        'start_date'       => 'date',
        'end_date'         => 'date',
        'grace_period_end' => 'date',
    ];

    public function usaha() { return $this->belongsTo(Usaha::class, 'usaha_id'); }
    public function plan()  { return $this->belongsTo(Plan::class, 'plan_id'); }

    // Helper: apakah dalam grace period
    public function isInGracePeriod(): bool
    {
        if (!$this->grace_period_end) return false;
        return now()->toDateString() <= $this->grace_period_end->toDateString()
               && now()->toDateString() > $this->end_date->toDateString();
    }

    // Helper: sisa hari sampai expired
    public function sisaHari(): int
    {
        if (!$this->end_date) return 9999; // lifetime
        return max(0, (int) now()->diffInDays($this->end_date, false));
    }
}