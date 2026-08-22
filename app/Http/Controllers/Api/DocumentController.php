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
            // Admin sees all documents.
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

        return response()->json(
            $query->orderByDesc('created_at')->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated.'
            ], 401);
        }

        if (!in_array($user->role, [
            'admin',
            'lawyer',
            'consultant',
            'client'
        ], true)) {
            return response()->json([
                'error' => 'Unauthorized'
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | Validation
        |--------------------------------------------------------------------------
        |
        | Support both:
        | 1. multipart/form-data with file
        | 2. JSON with base64 "data"
        |
        */

        $hasFile = $request->hasFile('file');
        $hasBase64 = $request->filled('data');

        /*
         * إذا وصل ملف حقيقي:
         * نأخذ الاسم من الملف تلقائياً.
         */
        $detectedFileName = null;

        if ($hasFile) {
            $detectedFileName = $request->file('file')
                ->getClientOriginalName();
        } elseif ($request->filled('file_name')) {
            $detectedFileName = $request->input('file_name');
        }

        /*
         * العنوان والتصنيف لم يعدا يسببان 422
         * إذا لم ترسلهما الواجهة.
         */
        $title = trim(
            (string) $request->input(
                'title',
                $detectedFileName ?: 'مستند'
            )
        );

        if ($title === '') {
            $title = $detectedFileName ?: 'مستند';
        }

        $category = trim(
            (string) $request->input(
                'category',
                'other'
            )
        );

        if (!in_array($category, [
            'contract',
            'memo',
            'poa',
            'hearing_related',
            'other'
        ], true)) {
            $category = 'other';
        }

        /*
         * Validate باقي البيانات.
         */
        $validator = Validator::make($request->all(), [
            'case_id' => 'nullable|integer|exists:cases,id',
            'hearing_id' => 'nullable|integer|exists:hearings,id',
            'file' => 'nullable|file|max:51200',
            'data' => 'nullable|string',
            'file_name' => 'nullable|string|max:255',
            'mime_type' => 'nullable|string|max:128',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'بيانات الرفع غير صحيحة',
                'errors' => $validator->errors(),
            ], 422);
        }

        /*
         * لازم يكون فيه ملف أو Base64.
         */
        if (!$hasFile && !$hasBase64) {
            return response()->json([
                'message' => 'لم يتم إرسال ملف',
                'error' => 'File upload or base64 data is required'
            ], 422);
        }

        $fileKey = null;
        $fileUrl = null;
        $fileName = $detectedFileName ?: 'file';
        $mimeType = 'application/octet-stream';
        $sizeBytes = 0;

        /*
        |--------------------------------------------------------------------------
        | الطريقة الأولى: رفع ملف حقيقي
        |--------------------------------------------------------------------------
        */
        if ($hasFile) {

            $uploadedFile = $request->file('file');

            if (!$uploadedFile->isValid()) {
                return response()->json([
                    'message' => 'الملف غير صالح أو لم يتم رفعه بشكل صحيح'
                ], 422);
            }

            $originalName = $uploadedFile->getClientOriginalName();

            $fileName = $originalName ?: 'file';

            $sanitizedName = $this->sanitizeFileName(
                $fileName
            );

            $mimeType =
                $uploadedFile->getClientMimeType()
                ?: $uploadedFile->getMimeType()
                ?: 'application/octet-stream';

            $sizeBytes =
                $uploadedFile->getSize() ?: 0;

            $directory = 'documents/' . date('Y/m');

            $storedName =
                Str::random(16) . '_' . $sanitizedName;

            try {
                $fileKey = Storage::disk('b2')->putFileAs(
                    $directory,
                    $uploadedFile,
                    $storedName
                );
            } catch (\Throwable $e) {
                return response()->json([
                    'message' => 'تعذر حفظ الملف في التخزين',
                    'error' => $e->getMessage(),
                ], 500);
            }

            if (!$fileKey) {
                return response()->json([
                    'message' => 'فشل حفظ الملف'
                ], 500);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | الطريقة الثانية: Base64
        |--------------------------------------------------------------------------
        */
        else {

            $rawData = (string) $request->input('data');

            $decodedData =
                $this->decodeBase64File($rawData);

            if ($decodedData === false) {
                return response()->json([
                    'message' => 'بيانات الملف غير صالحة',
                    'error' => 'Invalid base64 file data'
                ], 422);
            }

            $fileName =
                basename(
                    (string) $request->input(
                        'file_name',
                        'file'
                    )
                );

            if ($fileName === '' || $fileName === '.') {
                $fileName = 'file';
            }

            $mimeType =
                $request->input(
                    'mime_type',
                    $this->extractMimeTypeFromData($rawData)
                    ?: 'application/octet-stream'
                );

            $sanitizedName =
                $this->sanitizeFileName($fileName);

            $fileKey =
                'documents/' .
                date('Y/m') .
                '/' .
                Str::random(16) .
                '_' .
                $sanitizedName;

            $sizeBytes =
                strlen($decodedData);

            try {
                $stored = Storage::disk('b2')->put(
                    $fileKey,
                    $decodedData
                );
            } catch (\Throwable $e) {
                return response()->json([
                    'message' => 'تعذر حفظ الملف في التخزين',
                    'error' => $e->getMessage(),
                ], 500);
            }

            if (!$stored) {
                return response()->json([
                    'message' => 'فشل حفظ الملف'
                ], 500);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Create database record
        |--------------------------------------------------------------------------
        */
        try {

            $document = Document::create([
                'title' => $title,
                'category' => $category,

                'case_id' =>
                    $request->input('case_id'),

                'hearing_id' =>
                    $request->input('hearing_id'),

                'uploader_id' =>
                    $user->id,

                'uploader_role' =>
                    $user->role,

                'file_name' =>
                    $fileName,

                'file_key' =>
                    $fileKey,

                'file_url' =>
                    null,

                'mime_type' =>
                    $mimeType,

                'size_bytes' =>
                    $sizeBytes,
            ]);

        } catch (\Throwable $e) {

            /*
             * إذا حفظ الملف لكن فشل إنشاء السجل،
             * نحاول حذف الملف حتى لا يبقى ملف بدون سجل.
             */
            if ($fileKey) {
                try {
                    Storage::disk('b2')->delete($fileKey);
                } catch (\Throwable $ignored) {
                }
            }

            return response()->json([
                'message' => 'تعذر حفظ بيانات المستند',
                'error' => $e->getMessage(),
            ], 500);
        }

        /*
        |--------------------------------------------------------------------------
        | Download URL
        |--------------------------------------------------------------------------
        */
        try {
            $document->update([
                'file_url' => route(
                    'documents.download',
                    ['id' => $document->id]
                ),
            ]);
        } catch (\Throwable $e) {
            // لا نفشل الرفع بسبب مشكلة في إنشاء الرابط.
        }

        /*
        |--------------------------------------------------------------------------
        | Audit Log
        |--------------------------------------------------------------------------
        */
        try {

            AuditLog::create([
                'actor_id' =>
                    $user->id,

                'actor_role' =>
                    $user->role,

                'action' =>
                    'document.upload',

                'target_type' =>
                    'document',

                'target_id' =>
                    $document->id,

                'details' =>
                    $document->title,

                'ip_address' =>
                    $request->ip(),
            ]);

        } catch (\Throwable $e) {
            // السجل التدقيقي لا يمنع نجاح رفع المستند.
        }

        /*
        |--------------------------------------------------------------------------
        | Success
        |--------------------------------------------------------------------------
        */
        return response()->json([
            'message' => 'تم رفع المستند وحفظه بنجاح',
            'id' => $document->id,
            'document' => $document,
        ], 201);
    }

    public function download(
        $id,
        Request $request
    ) {
        $document = Document::find($id);

        if (!$document) {
            return response()->json([
                'error' => 'Document not found'
            ], 404);
        }

        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated.'
            ], 401);
        }

        if ($user->role !== 'admin') {

            $allowed =
                $document->uploader_id === $user->id;

            if (!$allowed && $document->case_id) {

                $allowed = LegalCase::where(
                    'id',
                    $document->case_id
                )
                ->where(function ($q) use ($user) {

                    if ($user->role === 'lawyer') {
                        $q->where(
                            'lawyer_id',
                            $user->id
                        );
                    }

                    elseif ($user->role === 'consultant') {
                        $q->where(
                            'consultant_id',
                            $user->id
                        );
                    }

                    elseif ($user->role === 'client') {
                        $q->where(
                            'client_id',
                            $user->id
                        );
                    }

                })
                ->exists();
            }

            if (!$allowed) {
                return response()->json([
                    'error' => 'Unauthorized'
                ], 403);
            }
        }

        if (
            !$document->file_key ||
            !Storage::disk('b2')->exists(
                $document->file_key
            )
        ) {
            return response()->json([
                'error' => 'File not found'
            ], 404);
        }

        return Storage::disk('b2')->download(
            $document->file_key,
            $document->file_name,
            [
                'Content-Type' =>
                    $document->mime_type
                    ?: 'application/octet-stream',

                'Content-Disposition' =>
                    'inline; filename="' .
                    addslashes(
                        $document->file_name
                    ) .
                    '"',
            ]
        );
    }

    public function destroy(
        $id,
        Request $request
    ): JsonResponse {

        $document = Document::find($id);

        if (!$document) {
            return response()->json([
                'error' => 'Document not found'
            ], 404);
        }

        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated.'
            ], 401);
        }

        if ($user->role !== 'admin') {

            $allowed =
                $document->uploader_id === $user->id;

            if (!$allowed && $document->case_id) {

                $allowed = LegalCase::where(
                    'id',
                    $document->case_id
                )
                ->where(function ($q) use ($user) {

                    if ($user->role === 'lawyer') {
                        $q->where(
                            'lawyer_id',
                            $user->id
                        );
                    }

                    elseif ($user->role === 'consultant') {
                        $q->where(
                            'consultant_id',
                            $user->id
                        );
                    }

                    elseif ($user->role === 'client') {
                        $q->where(
                            'client_id',
                            $user->id
                        );
                    }

                })
                ->exists();
            }

            if (!$allowed) {
                return response()->json([
                    'error' => 'Unauthorized'
                ], 403);
            }
        }

        if (
            $document->file_key &&
            Storage::disk('b2')->exists(
                $document->file_key
            )
        ) {
            Storage::disk('b2')->delete(
                $document->file_key
            );
        }

        $documentId =
            $document->id;

        $documentTitle =
            $document->title;

        $document->delete();

        try {

            AuditLog::create([
                'actor_id' =>
                    $user->id,

                'actor_role' =>
                    $user->role,

                'action' =>
                    'document.delete',

                'target_type' =>
                    'document',

                'target_id' =>
                    $documentId,

                'details' =>
                    $documentTitle,

                'ip_address' =>
                    $request->ip(),
            ]);

        } catch (\Throwable $e) {
        }

        return response()->json([
            'message' =>
                'Document deleted successfully'
        ]);
    }

    private function sanitizeFileName(
        string $fileName
    ): string {

        $fileName =
            basename($fileName);

        $fileName =
            preg_replace(
                '/[^A-Za-z0-9._-]+/',
                '_',
                $fileName
            );

        return trim(
            $fileName,
            '_'
        ) ?: 'file';
    }

    private function decodeBase64File(
        string $data
    ): string|false {

        if (
            str_contains(
                $data,
                ';base64,'
            )
        ) {

            [, $base64] =
                explode(
                    ';base64,',
                    $data,
                    2
                );

            return base64_decode(
                $base64,
                true
            );
        }

        return base64_decode(
            $data,
            true
        );
    }

    private function extractMimeTypeFromData(
        string $data
    ): ?string {

        if (
            preg_match(
                '/^data:([^;]+);base64,/',
                $data,
                $matches
            )
        ) {
            return $matches[1];
        }

        return null;
    }
}
