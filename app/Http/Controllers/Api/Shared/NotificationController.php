<?php
namespace App\Http\Controllers\Api\Shared;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Traits\HasPagination;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    use HasPagination;

    public function index(Request $request)
    {
        $notifs = Notification::where('user_id', auth('api')->id() ?? auth()->id())
                              ->latest()
                              ->paginate($this->getPerPage($request));

        return response()->json($this->paginateResponse($notifs, 'Notifikasi'));
    }
    
    public function getUnreadCount()
    {
        $count = Notification::where('user_id', auth('api')->id() ?? auth()->id())
                             ->where('is_read', 'false')
                             ->count();
                             
        return response()->json([
            'success' => true,
            'data' => [
                'unread_count' => $count
            ]
        ]);
    }

    public function markRead(string $id)
    {
        Notification::where('id', $id)->where('user_id', auth('api')->id() ?? auth()->id())->update(['is_read' => true]);
        return response()->json(['message' => 'Notifikasi ditandai sudah dibaca']);
    }

    public function markAllRead()
    {
        Notification::where('user_id', auth('api')->id() ?? auth()->id())->update(['is_read' => true]);
        return response()->json(['message' => 'Semua notifikasi ditandai sudah dibaca']);
    }
}
