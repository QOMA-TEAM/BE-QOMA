<?php
namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    protected $table = 'users';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'role_id', 'usaha_id', 'outlet_id', 'deskripsi_usaha',
        'username', 'nama_lengkap', 'email', 'password', 'is_active','telp_usaha',
        'status', 'catatan_admin', 'no_telp', 'approved_at', 'rejected_at',
    ];
    protected $hidden = ['password'];
    protected $casts  = ['is_active' => 'boolean'];

    public function getJWTIdentifier() { return $this->getKey(); }
    public function getJWTCustomClaims() { return []; }

    public function role()   { return $this->belongsTo(Role::class, 'role_id'); }
    public function usaha()  { return $this->belongsTo(Usaha::class, 'usaha_id'); }
    public function outlet() { return $this->belongsTo(Outlet::class, 'outlet_id'); }
}