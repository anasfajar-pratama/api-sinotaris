<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\AjbCase;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    public function documents(Request $request): JsonResponse
    {
        $query = Document::with(['documentType', 'client'])
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->date_from, fn ($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->date_to, fn ($q) => $q->whereDate('created_at', '<=', $request->date_to))
            ->latest();

        return response()->json($query->paginate(50));
    }

    public function ajb(Request $request): JsonResponse
    {
        $query = AjbCase::with(['document.client', 'sellers', 'buyers'])
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->latest();

        return response()->json($query->paginate(50));
    }

    public function clients(Request $request): JsonResponse
    {
        $clients = Client::withCount('documents')
            ->with(['documents' => fn ($q) => $q->select('id', 'client_id', 'status')])
            ->latest()
            ->paginate(50);

        return response()->json($clients);
    }

    public function exportPdf(Request $request)
    {
        $documents = Document::with(['documentType', 'client'])->latest()->take(100)->get();

        // Simple PDF via dompdf
        $html = '<h1>Laporan Dokumen - SiNotaris</h1><p>Tanggal: ' . now()->format('d/m/Y H:i') . '</p>';
        $html .= '<table border="1" width="100%" cellpadding="5"><thead><tr><th>No. Dokumen</th><th>Judul</th><th>Klien</th><th>Jenis</th><th>Status</th><th>Tgl Dibuat</th></tr></thead><tbody>';
        foreach ($documents as $doc) {
            $html .= '<tr><td>' . $doc->doc_number . '</td><td>' . $doc->title . '</td><td>' . ($doc->client?->name ?? '-') . '</td><td>' . ($doc->documentType?->name ?? '-') . '</td><td>' . $doc->status . '</td><td>' . $doc->created_at->format('d/m/Y') . '</td></tr>';
        }
        $html .= '</tbody></table>';

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
        return $pdf->download('laporan-dokumen-' . now()->format('Ymd') . '.pdf');
    }

    public function exportExcel(Request $request)
    {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\DocumentsExport(),
            'laporan-dokumen-' . now()->format('Ymd') . '.xlsx'
        );
    }
}
