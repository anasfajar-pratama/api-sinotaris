<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Client;
use App\Models\AjbCase;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function stats(Request $request): JsonResponse
    {
        $totalDocuments    = Document::count();
        $inProgressDocs    = Document::where('status', 'in_progress')->count();
        $completedDocs     = Document::where('status', 'completed')->count();
        $pendingReview     = Document::where('status', 'review')->count();
        $totalClients      = Client::count();
        $totalAjbCases     = AjbCase::count();
        $activeAjbCases    = AjbCase::where('status', 'active')->count();
        $overdueDocuments  = Document::whereNotNull('deadline')
            ->where('deadline', '<', now())
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->count();

        // Monthly completion rate
        $thisMonthCompleted = Document::where('status', 'completed')
            ->whereMonth('updated_at', now()->month)
            ->count();

        return response()->json([
            'total_documents'     => $totalDocuments,
            'in_progress'         => $inProgressDocs,
            'completed'           => $completedDocs,
            'pending_review'      => $pendingReview,
            'total_clients'       => $totalClients,
            'total_ajb'           => $totalAjbCases,
            'active_ajb'          => $activeAjbCases,
            'overdue'             => $overdueDocuments,
            'monthly_completed'   => $thisMonthCompleted,
        ]);
    }

    public function recentActivity(Request $request): JsonResponse
    {
        $activities = ActivityLog::with('user')
            ->latest()
            ->take(20)
            ->get()
            ->map(fn ($log) => [
                'id'         => $log->id,
                'user'       => $log->user?->name ?? 'System',
                'action'     => $log->action,
                'module'     => $log->module,
                'record_id'  => $log->record_id,
                'created_at' => $log->created_at,
            ]);

        return response()->json(['activities' => $activities]);
    }

    public function chartData(Request $request): JsonResponse
    {
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $months[] = [
                'month'     => $date->format('M Y'),
                'completed' => Document::where('status', 'completed')
                    ->whereYear('updated_at', $date->year)
                    ->whereMonth('updated_at', $date->month)
                    ->count(),
                'created'   => Document::whereYear('created_at', $date->year)
                    ->whereMonth('created_at', $date->month)
                    ->count(),
            ];
        }

        $byStatus = Document::select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->get()
            ->map(fn ($item) => ['status' => $item->status, 'total' => $item->total]);

        $byType = Document::join('document_types', 'documents.type_id', '=', 'document_types.id')
            ->select('document_types.name', DB::raw('count(*) as total'))
            ->groupBy('document_types.name')
            ->take(5)
            ->get();

        return response()->json([
            'monthly_trend' => $months,
            'by_status'     => $byStatus,
            'by_type'       => $byType,
        ]);
    }

    public function upcomingDeadlines(Request $request): JsonResponse
    {
        $deadlines = Document::with(['client', 'documentType'])
            ->whereNotNull('deadline')
            ->where('deadline', '>=', now())
            ->where('deadline', '<=', now()->addDays(14))
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->orderBy('deadline')
            ->take(10)
            ->get()
            ->map(fn ($doc) => [
                'id'          => $doc->id,
                'doc_number'  => $doc->doc_number,
                'title'       => $doc->title,
                'client_name' => $doc->client?->name,
                'deadline'    => $doc->deadline,
                'days_left'   => now()->diffInDays($doc->deadline, false),
                'priority'    => $doc->priority,
                'status'      => $doc->status,
            ]);

        return response()->json(['deadlines' => $deadlines]);
    }

    public function activityLogs(Request $request): JsonResponse
    {
        $logs = ActivityLog::with('user')
            ->when($request->user_id, fn ($q) => $q->where('user_id', $request->user_id))
            ->when($request->module, fn ($q) => $q->where('module', $request->module))
            ->latest()
            ->paginate(50);

        return response()->json($logs);
    }
}
