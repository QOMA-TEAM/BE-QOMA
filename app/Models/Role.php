<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $table = 'roles';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false; // ← TAMBAHKAN INI

    protected $fillable = ['id', 'name'];

    public function users() { return $this->hasMany(User::class, 'role_id'); }
}
