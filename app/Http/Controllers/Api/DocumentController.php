<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\LegalCase;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class DocumentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Document::with(['case', 'uploader']);

        if ($user->role === 'admin') {
            // Admins see all documents
        } elseif ($user->role === 'lawyer') {
            $caseIds = LegalCase::where('lawyer_id', $user->id)->pluck('id');
            $query->where(function ($q) use ($user, $caseIds) {
                $q->where('uploader_id', $user->id)
                    ->orWhereIn('case_id', $caseIds);
            });
        } elseif ($user->role === 'consultant') {
            $caseIds = LegalCase::where('consultant_id', $user->id)->pluck('id');
            $query->where(function ($q) use ($user, $caseIds) {
                $q->where('uploader_id', $user->id)
                    ->orWhereIn('case_id', $caseIds);
            });
        } else {
            $caseIds = LegalCase::where('client_id', $user->id)->pluck('id');
            $query->where(function ($q) use ($user, $caseIds) {
                $q->where('uploader_id', $user->id)
                    ->orWhereIn('case_id', $caseIds);
            });
        }

        if ($request->filled('case_id')) {
            $query->where('case_id', $request->case_id);
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        return response()->json($query->orderByDesc('created_at')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!in_array($user->role, ['admin', 'lawyer', 'consultant', 'client'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|min:2|max:255',
            'category' => 'required|in:contract,memo,poa,hearing_related,other',
            'case_id' => 'nullable|exists:cases,id',
            'hearing_id' => 'nullable|exists:hearings,id',
            'file' => 'nullable|file|max:25600',
            'data' => 'nullable|string',
            'file_name' => 'required_without:file|string|max:255',
            'mime_type' => 'nullable|string|max:128',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if (!$request->hasFile('file') && !$request->filled('data')) {
            return response()->json(['error' => 'File upload or base64 data is required'], 422);
        }

        if ($request->hasFile('file')) {
            $uploadedFile = $request->file('file');
            $originalName = $uploadedFile->getClientOriginalName();
            $sanitizedName = $this->sanitizeFileName($originalName);
            $fileKey = Storage::disk('b2')->putFileAs(
                'documents/' . date('Y/m'),
                $uploadedFile,
                Str::random(16) . '_' . $sanitizedName
            );
            $fileUrl = route('documents.download', ['id' => $document->id ?? 0]);
            $mimeType = $uploadedFile->getClientMimeType() ?: 'application/octet-stream';
            $sizeBytes = $uploadedFile->getSize();
            $fileName = $originalName;
        } else {
            $rawData = $request->input('data');
            $decodedData = $this->decodeBase64File($rawData);
            if ($decodedData === false) {
                return response()->json(['error' => 'Invalid base64 file data'], 422);
            }

            $fileName = basename($request->input('file_name'));
            $mimeType = $request->input('mime_type', $this->extractMimeTypeFromData($rawData) ?? 'application/octet-stream');
            $sanitizedName = $this->sanitizeFileName($fileName);
            $fileKey = 'documents/' . date('Y/m') . '/' . Str::random(16) . '_' . $sanitizedName;
            Storage::disk('b2')->put($fileKey, $decodedData);
            $fileUrl = route('documents.download', ['id' => $document->id ?? 0]);
            $sizeBytes = strlen($decodedData);
        }

        $document = Document::create([
            'title' => $request->title,
            'category' => $request->category,
            'case_id' => $request->case_id,
            'hearing_id' => $request->hearing_id,
            'uploader_id' => $user->id,
            'uploader_role' => $user->role,
            'file_name' => $fileName,
            'file_key' => $fileKey,
            'file_url' => $fileUrl,
            'mime_type' => $mimeType,
            'size_bytes' => $sizeBytes,
        ]);

        $document->update([
            'file_url' => route('documents.download', ['id' => $document->id]),
        ]);

        AuditLog::create([
            'actor_id' => $user->id,
            'actor_role' => $user->role,
            'action' => 'document.upload',
            'target_type' => 'document',
            'target_id' => $document->id,
            'details' => $document->title,
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['id' => $document->id], 201);
    }


    public function download($id, Request $request)
    {
        $document = Document::find($id);

        if (!$document) {
            return response()->json(['error' => 'Document not found'], 404);
        }

        $user = $request->user();

        if ($user->role !== 'admin') {
            $allowed = $document->uploader_id === $user->id;

            if (!$allowed && $document->case_id) {
                $allowed = LegalCase::where('id', $document->case_id)
                    ->where(function ($q) use ($user) {
                        if ($user->role === 'lawyer') {
                            $q->where('lawyer_id', $user->id);
                        } elseif ($user->role === 'consultant') {
                            $q->where('consultant_id', $user->id);
                        } elseif ($user->role === 'client') {
                            $q->where('client_id', $user->id);
                        }
                    })->exists();
            }

            if (!$allowed) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }
        }

        if (!$document->file_key || !Storage::disk('b2')->exists($document->file_key)) {
            return response()->json(['error' => 'File not found'], 404);
        }

        return Storage::disk('b2')->download(
            $document->file_key,
            $document->file_name,
            [
                'Content-Type' => $document->mime_type ?: 'application/octet-stream',
                'Content-Disposition' => 'inline; filename="' . addslashes($document->file_name) . '"',
            ]
        );
    }

    public function destroy($id, Request $request): JsonResponse
    {
        $document = Document::find($id);
        if (!$document) {
            return response()->json(['error' => 'Document not found'], 404);
        }

        $user = $request->user();

        if ($user->role !== 'admin') {
            $allowed = $document->uploader_id === $user->id;
            if (!$allowed && $document->case_id) {
                $allowed = LegalCase::where('id', $document->case_id)
                    ->where(function ($q) use ($user) {
                        if ($user->role === 'lawyer') {
                            $q->where('lawyer_id', $user->id);
                        } elseif ($user->role === 'consultant') {
                            $q->where('consultant_id', $user->id);
                        } elseif ($user->role === 'client') {
                            $q->where('client_id', $user->id);
                        }
                    })->exists();
            }

            if (!$allowed) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }
        }

        if ($document->file_key && Storage::disk('b2')->exists($document->file_key)) {
            Storage::disk('b2')->delete($document->file_key);
        }

        $document->delete();

        AuditLog::create([
            'actor_id' => $user->id,
            'actor_role' => $user->role,
            'action' => 'document.delete',
            'target_type' => 'document',
            'target_id' => $document->id,
            'details' => $document->title,
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['message' => 'Document deleted successfully']);
    }

    private function sanitizeFileName(string $fileName): string
    {
        $fileName = basename($fileName);
        $fileName = preg_replace('/[^A-Za-z0-9._-]+/', '_', $fileName);
        return trim($fileName, '_') ?: 'file';
    }

    private function decodeBase64File(string $data): string|false
    {
        if (str_contains($data, ';base64,')) {
            [, $base64] = explode(';base64,', $data, 2);
            return base64_decode($base64, true);
        }

        return base64_decode($data, true);
    }

    private function extractMimeTypeFromData(string $data): ?string
    {
        if (preg_match('/^data:([^;]+);base64,/', $data, $matches)) {
            return $matches[1];
        }

        return null;
    }
}