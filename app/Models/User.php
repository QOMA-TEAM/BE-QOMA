<?php
namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use SoftDeletes;
    protected $table = 'users';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'role_id', 'usaha_id', 'outlet_id', 'deskripsi_usaha',
        'username', 'nama_lengkap', 'email', 'password', 'is_active','telp_usaha',
        'status', 'catatan_admin', 'no_telp', 'approved_at', 'rejected_at',
    ];
    protected $hidden = ['password'];
    protected $casts  = [];

    protected function isActive(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: fn ($value) => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            set: fn ($value) => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false',
        );
    }

    public function getJWTIdentifier() { return $this->getKey(); }
    public function getJWTCustomClaims() { return []; }

    public function role()   { return $this->belongsTo(Role::class, 'role_id'); }
    public function usaha()  { return $this->belongsTo(Usaha::class, 'usaha_id'); }
    public function outlet() { return $this->belongsTo(Outlet::class, 'outlet_id'); }

    public function passwordHistories()
    {
        return $this->hasMany(PasswordHistory::class);
    }

    protected static function booted()
    {
        static::created(function ($user) {
            if ($user->password) {
                $user->passwordHistories()->create([
                    'id' => \Illuminate\Support\Str::uuid(),
                    'password' => $user->password,
                ]);
            }
        });

        static::updated(function ($user) {
            if ($user->wasChanged('password')) {
                $user->passwordHistories()->create([
                    'id' => \Illuminate\Support\Str::uuid(),
                    'password' => $user->password,
                ]);
            }
        });
    }
}
