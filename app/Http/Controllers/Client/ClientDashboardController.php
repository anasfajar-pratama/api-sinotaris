<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ClientDashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user   = $request->user();
        $client = $user->client;

        if (!$client) {
            return response()->json([
                'total_documents'     => 0,
                'active_documents'    => 0,
                'completed_documents' => 0,
                'recent_documents'    => [],
                'unread_notifications' => 0,
            ]);
        }

        $totalDocuments     = Document::where('client_id', $client->id)->count();
        $activeDocuments    = Document::where('client_id', $client->id)->whereIn('status', ['draft', 'in_progress', 'review'])->count();
        $completedDocuments = Document::where('client_id', $client->id)->where('status', 'completed')->count();

        $recentDocuments = Document::with('documentType')
            ->where('client_id', $client->id)
            ->latest()
            ->take(5)
            ->get()
            ->map(fn ($doc) => [
                'id'          => $doc->id,
                'doc_number'  => $doc->doc_number,
                'title'       => $doc->title,
                'status'      => $doc->status,
                'current_stage' => $doc->current_stage,
                'deadline'    => $doc->deadline,
                'document_type' => ['name' => $doc->documentType?->name],
                'created_at'  => $doc->created_at,
            ]);

        $unreadNotifications = Notification::where('user_id', $user->id)->where('is_read', false)->count();

        return response()->json([
            'total_documents'      => $totalDocuments,
            'active_documents'     => $activeDocuments,
            'completed_documents'  => $completedDocuments,
            'recent_documents'     => $recentDocuments,
            'unread_notifications' => $unreadNotifications,
        ]);
    }
}
