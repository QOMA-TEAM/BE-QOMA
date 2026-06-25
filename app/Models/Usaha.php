<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Usaha extends Model
{
    protected $table = 'usaha';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'nama_usaha', 'email', 'alamat', 'owner_id', // ← tambah alamat
        'status', 'catatan_admin', 'approved_at', 'rejected_at',
    ];
    protected $casts = [
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function owner()         { return $this->belongsTo(User::class, 'owner_id'); }
    public function outlets()       { return $this->hasMany(Outlet::class, 'usaha_id'); }
    public function subscription()  { return $this->hasOne(Subscription::class, 'usaha_id')->latest(); }
    public function menus()         { return $this->hasMany(Menu::class, 'usaha_id'); }
    public function kategoriMenus() { return $this->hasMany(KategoriMenu::class, 'usaha_id'); }
    public function bahanMasters()  { return $this->hasMany(BahanMaster::class, 'usaha_id'); }
    public function rejections()    { return $this->hasMany(UsahaRejection::class, 'usaha_id'); }
}
