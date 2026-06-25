<?php
namespace App\Services;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Str;

class NotificationService
{
    /**
     * Kirim notifikasi ke semua super_admin
     */
    public static function notifySuperAdmins(string $title, string $message, string $type, array $data = []): void
    {
        $superAdmins = User::whereHas('role', fn($q) => $q->where('name', 'super_admin'))
                           ->where('is_active', 'true')
                           ->get();

        foreach ($superAdmins as $admin) {
            Notification::create([
                'id'      => Str::uuid(),
                'user_id' => $admin->id,
                'title'   => $title,
                'message' => $message,
                'type'    => $type,
                'data'    => $data ?: null,
            ]);
        }
    }

    /**
     * Kirim notifikasi ke user tertentu
     */
    public static function notify(string $userId, string $title, string $message, string $type, array $data = []): void
    {
        Notification::create([
            'id'      => Str::uuid(),
            'user_id' => $userId,
            'title'   => $title,
            'message' => $message,
            'type'    => $type,
            'data'    => $data ?: null,
        ]);
    }
}
