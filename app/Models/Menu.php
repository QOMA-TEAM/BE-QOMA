<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Menu extends Model
{
    protected $table = 'menu';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $fillable = [
        'id', 'usaha_id', 'kategori_id', 'nama',
        'harga_default', 'gambar', 'keterangan', 'is_active',
    ];
    protected $casts = ['harga_default' => 'decimal:2', 'is_active' => 'boolean'];

    public function kategori()    { return $this->belongsTo(KategoriMenu::class, 'kategori_id'); }
    public function usaha()       { return $this->belongsTo(Usaha::class, 'usaha_id'); }
    public function menuOutlets() { return $this->hasMany(MenuOutlet::class, 'menu_id'); }
    public function bahanMasters(){ return $this->belongsToMany(BahanMaster::class, 'menu_bahan', 'menu_id', 'bahan_master_id')->withPivot('jumlah_pakai')->withTimestamps(); }

    public function addons()
    {
        return $this->belongsToMany(Addon::class, 'menu_addon', 'menu_id', 'addon_id')
                    ->withTimestamps();
    }

    public function toArray()
    {
        $array = parent::toArray();
        if (!empty($array['gambar']) && !str_starts_with($array['gambar'], 'http')) {
            $array['gambar'] = app(\App\Services\ImageService::class)->url($array['gambar']);
        }
        return $array;
    }
}


