<?php
namespace App\Services;
use App\Models\ActivityLog;
use Illuminate\Support\Str;

class ActivityLogService
{
    public static function log(
        string  $aktivitas,
        string  $deskripsi,
        array   $metadata = [],
        ?string $usahaId  = null,
        ?string $outletId = null,
    ): void {
        if (!$usahaId && auth()->check()) {
            $usahaId = auth()->user()->usaha_id;
        } elseif (!$usahaId && $outletId) {
            $usahaId = \App\Models\Outlet::where('id', $outletId)->value('usaha_id');
        }

        if (!$outletId && auth()->check()) {
            $outletId = auth()->user()->outlet_id;
        }

        ActivityLog::create([
            'id'         => Str::uuid(),
            'user_id'    => auth()->id(),
            'usaha_id'   => $usahaId,
            'outlet_id'  => $outletId,
            'aktivitas'  => $aktivitas,
            'deskripsi'  => $deskripsi,
            'metadata'   => $metadata ?: null,
            'ip_address' => request()->ip(),
        ]);
    }
}