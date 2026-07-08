<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\NotificationTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = Notification::where('user_id', $request->user()->id)
            ->latest()
            ->paginate(30);

        return response()->json($notifications);
    }

    public function markRead(Request $request, int $id): JsonResponse
    {
        $notification = Notification::where('user_id', $request->user()->id)->findOrFail($id);
        $notification->update(['is_read' => true]);
        return response()->json(['message' => 'Notifikasi ditandai dibaca']);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        Notification::where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['message' => 'Semua notifikasi ditandai dibaca']);
    }

    public function templates(Request $request): JsonResponse
    {
        $templates = NotificationTemplate::all();
        return response()->json(['templates' => $templates]);
    }

    public function updateTemplate(Request $request, int $id): JsonResponse
    {
        $template = NotificationTemplate::findOrFail($id);
        $template->update($request->only(['subject', 'body', 'is_active']));
        return response()->json(['message' => 'Template berhasil diperbarui', 'template' => $template->fresh()]);
    }
}
