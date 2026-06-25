<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class LaporanKeuangan extends Model
{
    protected $table = 'laporan_keuangan';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'outlet_id',
        'total_pendapatan', 'total_pengeluaran',
        'total_kerugian', 'total_keuntungan',
        'periode', 'tipe_periode',
    ];

    protected $casts = [
        'total_pendapatan'  => 'decimal:2',
        'total_pengeluaran' => 'decimal:2',
        'total_kerugian'    => 'decimal:2',
        'total_keuntungan'  => 'decimal:2',
    ];

    public function outlet()
    {
        return $this->belongsTo(Outlet::class, 'outlet_id');
    }
}
