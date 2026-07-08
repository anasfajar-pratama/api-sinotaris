<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class ClientController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Client::withCount('documents')
            ->when($request->search, fn ($q) => $q->where('name', 'like', "%{$request->search}%")
                ->orWhere('nik', 'like', "%{$request->search}%")
                ->orWhere('email', 'like', "%{$request->search}%"))
            ->latest();

        return response()->json($query->paginate($request->per_page ?? 15));
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'   => 'required|string|max:255',
            'nik'    => 'nullable|string|max:20|unique:clients,nik',
            'phone'  => 'nullable|string|max:20',
            'email'  => 'nullable|email',
            'npwp'   => 'nullable|string|max:30',
            'address' => 'nullable|string',
            'gender' => 'nullable|in:male,female',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validasi gagal', 'errors' => $validator->errors()], 422);
        }

        $client = Client::create($request->only(['name', 'nik', 'phone', 'email', 'npwp', 'address', 'gender', 'birth_date', 'notes']));

        return response()->json(['message' => 'Klien berhasil ditambahkan', 'client' => $client], 201);
    }

    public function show(int $id): JsonResponse
    {
        $client = Client::with('user')->withCount('documents')->findOrFail($id);
        return response()->json(['client' => $client]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $client = Client::findOrFail($id);
        $client->update($request->only(['name', 'nik', 'phone', 'email', 'npwp', 'address', 'gender', 'birth_date', 'notes']));
        return response()->json(['message' => 'Klien berhasil diperbarui', 'client' => $client->fresh()]);
    }

    public function destroy(int $id): JsonResponse
    {
        Client::findOrFail($id)->delete();
        return response()->json(['message' => 'Klien berhasil dihapus']);
    }

    public function documents(Request $request, int $id): JsonResponse
    {
        $client = Client::findOrFail($id);
        $documents = Document::with('documentType')
            ->where('client_id', $id)
            ->latest()
            ->paginate(20);

        return response()->json($documents);
    }
}
