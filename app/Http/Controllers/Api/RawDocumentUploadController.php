<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\Hearing;
use App\Models\LegalCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RawDocumentUploadController extends Controller
{
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

        // استقبال الملف مباشرة كـ binary
        $raw = file_get_contents('php://input');

        if ($raw === false || strlen($raw) === 0) {
            return response()->json([
                'message' => 'لم يتم إرسال ملف',
                'error' => 'Empty upload body'
            ], 422);
        }

        $sizeBytes = strlen($raw);

        if ($sizeBytes > 50 * 1024 * 1024) {
            return response()->json([
                'message' => 'حجم الملف أكبر من 50 ميجابايت'
            ], 422);
        }

        $fileName = basename(
            (string) $request->query('file_name', 'file')
        );

        if ($fileName === '' || $fileName === '.') {
            $fileName = 'file';
        }

        $title = trim(
            (string) $request->query(
                'title',
                $fileName
            )
        );

        if ($title === '') {
            $title = $fileName;
        }

        $category = trim(
            (string) $request->query(
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

        $caseId = $request->query('case_id');
        $hearingId = $request->query('hearing_id');

        if (
            $caseId !== null &&
            (
                !is_numeric($caseId) ||
                !LegalCase::whereKey((int) $caseId)->exists()
            )
        ) {
            return response()->json([
                'message' => 'القضية المحددة غير موجودة'
            ], 422);
        }

        if (
            $hearingId !== null &&
            (
                !is_numeric($hearingId) ||
                !Hearing::whereKey((int) $hearingId)->exists()
            )
        ) {
            return response()->json([
                'message' => 'الجلسة المحددة غير موجودة'
            ], 422);
        }

        $mimeType = (string) $request->query(
            'mime_type',
            $request->header(
                'Content-Type',
                'application/octet-stream'
            )
        );

        $mimeType = explode(';', $mimeType)[0]
            ?: 'application/octet-stream';

        $sanitizedName = preg_replace(
            '/[^A-Za-z0-9._-]+/',
            '_',
            $fileName
        ) ?: 'file';

        $sanitizedName = trim(
            $sanitizedName,
            '_'
        ) ?: 'file';

        $fileKey =
            'documents/' .
            date('Y/m') .
            '/' .
            Str::random(16) .
            '_' .
            $sanitizedName;

        // التخزين الحقيقي في Backblaze B2
        try {
            $stored = Storage::disk('b2')->put(
                $fileKey,
                $raw
            );
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'تعذر حفظ الملف في التخزين',
                'error' => $e->getMessage()
            ], 500);
        }

        if (!$stored) {
            return response()->json([
                'message' => 'فشل حفظ الملف'
            ], 500);
        }

        // حفظ بيانات الملف في قاعدة البيانات
        try {
            $document = Document::create([
                'title' => $title,
                'category' => $category,
                'case_id' => $caseId !== null
                    ? (int) $caseId
                    : null,
                'hearing_id' => $hearingId !== null
                    ? (int) $hearingId
                    : null,
                'uploader_id' => $user->id,
                'uploader_role' => $user->role,
                'file_name' => $fileName,
                'file_key' => $fileKey,
                'file_url' => null,
                'mime_type' => $mimeType,
                'size_bytes' => $sizeBytes,
            ]);

            $document->update([
                'file_url' => route(
                    'documents.download',
                    ['id' => $document->id]
                ),
            ]);
        } catch (\Throwable $e) {

            try {
                Storage::disk('b2')->delete($fileKey);
            } catch (\Throwable $ignored) {
            }

            return response()->json([
                'message' => 'تعذر حفظ بيانات المستند',
                'error' => $e->getMessage()
            ], 500);
        }

        try {
            AuditLog::create([
                'actor_id' => $user->id,
                'actor_role' => $user->role,
                'action' => 'document.upload',
                'target_type' => 'document',
                'target_id' => $document->id,
                'details' => $document->title,
                'ip_address' => $request->ip(),
            ]);
        } catch (\Throwable $ignored) {
        }

        return response()->json([
            'message' => 'تم رفع المستند وحفظه بنجاح',
            'id' => $document->id,
            'document' => $document,
        ], 201);
    }
}
