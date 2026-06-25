<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BahanMaster extends Model
{
    use SoftDeletes;
    protected $table = 'bahan_master';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $fillable = ['id', 'usaha_id', 'nama', 'satuan', 'satuan_dasar', 'konversi_ke_dasar', 'harga_default', 'gambar'];
    protected $casts    = ['harga_default' => 'decimal:2', 'konversi_ke_dasar' => 'decimal:4'];

    public function usaha()        { return $this->belongsTo(Usaha::class, 'usaha_id'); }
    public function bahanOutlets() { return $this->hasMany(BahanOutlet::class, 'bahan_master_id'); }

    public function toArray()
    {
        $array = parent::toArray();
        if (!empty($array['gambar']) && !str_starts_with($array['gambar'], 'http')) {
            $array['gambar'] = app(\App\Services\ImageService::class)->url($array['gambar']);
        }
        return $array;
    }
}
