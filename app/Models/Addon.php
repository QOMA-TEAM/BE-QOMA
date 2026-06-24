<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Addon extends Model
{
    protected $table = 'addon';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $fillable = ['id', 'usaha_id', 'nama', 'harga'];
    protected $casts    = ['harga' => 'decimal:2'];

    public function usaha() { return $this->belongsTo(Usaha::class, 'usaha_id'); }
}
