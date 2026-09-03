<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentFile;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class ClientDocumentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user   = $request->user();
        $client = $user->client;

        if (!$client) {
            return response()->json(['data' => [], 'total' => 0]);
        }

        $documents = Document::with('documentType')
            ->where('client_id', $client->id)
            ->when($request->search, fn ($q) => $q->where('title', 'like', "%{$request->search}%"))
            ->latest()
            ->paginate(15);

        return response()->json($documents);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $user   = $request->user();
        $client = $user->client;

        if (!$client) {
            return response()->json(['message' => 'Klien tidak ditemukan'], 404);
        }

        $document = Document::with([
            'documentType',
            'stages',
            'files',
            'orderDocuments.documentCatalog',
            'actors.actorType',
            'actors.documents.documentCatalog',
            'assets.assetType',
            'assets.documents.documentCatalog',
        ])
            ->where('client_id', $client->id)
            ->findOrFail($id);

        return response()->json(['document' => $document]);
    }

    public function downloadFile(Request $request, int $id, int $fileId): mixed
    {
        $user   = $request->user();
        $client = $user->client;

        $document = Document::where('client_id', $client?->id)->findOrFail($id);
        $file     = DocumentFile::where('document_id', $id)->findOrFail($fileId);

        if (!Storage::disk('public')->exists($file->path)) {
            return response()->json(['message' => 'File tidak ditemukan'], 404);
        }

        return Storage::disk('public')->download($file->path, $file->original_name);
    }
}
