<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class KategoriMenu extends Model
{
    use SoftDeletes;
    protected $table = 'kategori_menu';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $fillable = ['id', 'usaha_id', 'nama'];

    public function menus() { return $this->hasMany(Menu::class, 'kategori_id'); }
}
