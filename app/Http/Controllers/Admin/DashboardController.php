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
    /**
     * Terapkan filter periode waktu ke query.
     * Param: period=day|week|month|year
     *   day   -> date (YYYY-MM-DD)
     *   week  -> year + week (1-53)
     *   month -> year + month (1-12)
     *   year  -> year
     * Tanpa param period -> tanpa filter (semua waktu).
     */
    private function periodScope($query, string $field, Request $request)
    {
        $period = $request->input('period');
        if (!$period) return $query;

        if ($period === 'day') {
            $date = $request->input('date');
            return $date ? $query->whereDate($field, $date) : $query;
        }

        if ($period === 'week') {
            $week = (int) $request->input('week', 0);
            if ($week < 1 || $week > 53) return $query;
            $year = (int) $request->input('year', now()->year);
            $start = now()->setISODate($year, $week)->startOfDay();
            $end = $start->copy()->endOfWeek()->endOfDay();
            return $query->whereBetween($field, [$start, $end]);
        }

        if ($period === 'month') {
            $month = (int) $request->input('month', 0);
            if ($month < 1 || $month > 12) return $query;
            $year = (int) $request->input('year', now()->year);
            return $query->whereYear($field, $year)->whereMonth($field, $month);
        }

        if ($period === 'year') {
            return $query->whereYear($field, (int) $request->input('year', now()->year));
        }

        return $query;
    }

    public function stats(Request $request): JsonResponse
    {
        $documents = Document::query();
        $this->periodScope($documents, 'created_at', $request);

        $completedInPeriod = (clone $documents)->where('status', 'completed')->count();

        $totalDocuments    = (clone $documents)->count();
        $inProgressDocs    = (clone $documents)->where('status', 'in_progress')->count();
        $completedDocs     = (clone $documents)->where('status', 'completed')->count();
        $pendingReview     = (clone $documents)->where('status', 'review')->count();
        $overdueDocuments  = (clone $documents)->whereNotNull('deadline')
            ->where('deadline', '<', now())
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->count();

        // Klien & AJB: klien baru / kasus dibuat dalam periode yang sama.
        $clients = Client::query();
        $this->periodScope($clients, 'created_at', $request);
        $totalClients = (clone $clients)->count();

        $ajbCases = AjbCase::query();
        $this->periodScope($ajbCases, 'created_at', $request);
        $totalAjbCases  = (clone $ajbCases)->count();
        $activeAjbCases = (clone $ajbCases)->where('status', 'active')->count();

        // Dokumen selesai dalam periode (berdasarkan updated_at saat status jadi completed).
        $completedQuery = Document::where('status', 'completed');
        $this->periodScope($completedQuery, 'updated_at', $request);
        $monthlyCompleted = (clone $completedQuery)->count();

        return response()->json([
            'total_documents'     => $totalDocuments,
            'in_progress'         => $inProgressDocs,
            'completed'           => $completedDocs,
            'pending_review'      => $pendingReview,
            'total_clients'       => $totalClients,
            'total_ajb'           => $totalAjbCases,
            'active_ajb'          => $activeAjbCases,
            'overdue'             => $overdueDocuments,
            'monthly_completed'   => $monthlyCompleted,
            'completed_in_period' => $completedInPeriod,
        ]);
    }

    public function recentActivity(Request $request): JsonResponse
    {
        $activities = ActivityLog::query();
        $this->periodScope($activities, 'created_at', $request);

        $activities = $activities->with('user')
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
        $period = $request->input('period');

        // Tren: tanpa filter = 6 bulan terakhir; day = 14 hari terakhir;
        // week = 8 minggu terakhir; month = 12 bulan terakhir; year = 12 bulan dlm tahun itu.
        $trend = match (true) {
            $period === 'day' && $request->input('date') => $this->dailyTrend($request),
            $period === 'week' && $request->input('week') => $this->weeklyTrend($request),
            $period === 'year' && $request->input('year') => $this->monthlyTrendForYear($request),
            $period === 'month' && $request->input('month') => $this->sixMonthTrend($request),
            default => $this->sixMonthTrend($request),
        };

        $documents = Document::query();
        $this->periodScope($documents, 'created_at', $request);
        $byStatus = (clone $documents)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->get()
            ->map(fn ($item) => ['status' => $item->status, 'total' => $item->total]);

        $byType = Document::join('document_types', 'documents.type_id', '=', 'document_types.id')
            ->whereIn('documents.id', (clone $documents)->pluck('documents.id'))
            ->select('document_types.name', DB::raw('count(*) as total'))
            ->groupBy('document_types.name')
            ->take(5)
            ->get();

        return response()->json([
            'monthly_trend' => $trend,
            'by_status'     => $byStatus,
            'by_type'       => $byType,
        ]);
    }

    private function dailyTrend(Request $request): array
    {
        $date = $request->input('date') ? \Carbon\Carbon::parse($request->input('date')) : now();
        $out = [];
        for ($i = 13; $i >= 0; $i--) {
            $d = $date->copy()->subDays($i);
            $out[] = [
                'month'     => $d->format('d M'),
                'completed' => Document::where('status', 'completed')->whereDate('updated_at', $d->toDateString())->count(),
                'created'   => Document::whereDate('created_at', $d->toDateString())->count(),
            ];
        }
        return $out;
    }

    private function weeklyTrend(Request $request): array
    {
        $year = (int) $request->input('year', now()->year);
        $week = (int) $request->input('week', now()->isoWeek());
        $out = [];
        for ($i = 7; $i >= 0; $i--) {
            $start = now()->setISODate($year, $week)->subWeeks($i)->startOfWeek();
            $end   = $start->copy()->endOfWeek()->endOfDay();
            $out[] = [
                'month'     => 'W' . $start->isoWeek() . ' ' . $start->format('M'),
                'completed' => Document::where('status', 'completed')->whereBetween('updated_at', [$start, $end])->count(),
                'created'   => Document::whereBetween('created_at', [$start, $end])->count(),
            ];
        }
        return $out;
    }

    private function monthlyTrendForYear(Request $request): array
    {
        $year = (int) $request->input('year', now()->year);
        $out = [];
        for ($m = 1; $m <= 12; $m++) {
            $date = \Carbon\Carbon::create($year, $m, 1);
            $out[] = [
                'month'     => $date->format('M'),
                'completed' => Document::where('status', 'completed')->whereYear('updated_at', $year)->whereMonth('updated_at', $m)->count(),
                'created'   => Document::whereYear('created_at', $year)->whereMonth('created_at', $m)->count(),
            ];
        }
        return $out;
    }

    private function sixMonthTrend(Request $request): array
    {
        $period = $request->input('period');
        $anchor = \Carbon\Carbon::now();
        if ($period === 'month') {
            $anchor = \Carbon\Carbon::create(
                (int) $request->input('year', now()->year),
                (int) $request->input('month', now()->month),
                1
            );
        }
        $out = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = $anchor->copy()->subMonths($i);
            $out[] = [
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
        return $out;
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
