```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class DocumentController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $documents = Document::where('uploader_id', $user->id)
            ->latest('created_at')
            ->get();

        return response()->json($documents);
    }

    public function store(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'message' => 'غير مصرح. يرجى تسجيل الدخول.',
                ], 401);
            }

            if (!$request->hasFile('file')) {
                return response()->json([
                    'message' => 'لم يتم إرسال ملف.',
                    'error' => 'file_is_required',
                ], 422);
            }

            $file = $request->file('file');

            if (!$file || !$file->isValid()) {
                return response()->json([
                    'message' => 'الملف المرسل غير صالح.',
                    'error' => 'invalid_file',
                ], 422);
            }

            $request->validate([
                'file' => [
                    'required',
                    'file',
                    'max:51200',
                ],
                'title' => [
                    'nullable',
                    'string',
                    'max:255',
                ],
                'category' => [
                    'nullable',
                    'string',
                    'max:100',
                ],
                'case_id' => [
                    'nullable',
                    'integer',
                ],
                'hearing_id' => [
                    'nullable',
                    'integer',
                ],
            ]);

            $originalName = $file->getClientOriginalName();
            $extension = strtolower($file->getClientOriginalExtension());

            $safeName = Str::uuid()->toString();

            if ($extension !== '') {
                $safeName .= '.' . $extension;
            }

            $fileKey = 'documents/' . $safeName;

            /*
             * التخزين الحقيقي للمشروع: Backblaze B2
             */
            Storage::disk('b2')->putFileAs(
                'documents',
                $file,
                $safeName
            );

            /*
             * نتأكد أن الملف وصل إلى B2.
             */
            if (!Storage::disk('b2')->exists($fileKey)) {
                return response()->json([
                    'message' => 'تم إرسال الملف ولكن لم يتم العثور عليه في التخزين.',
                    'error' => 'b2_storage_failed',
                ], 500);
            }

            /*
             * رابط الملف.
             *
             * AWS_URL / B2_ENDPOINT يتم ضبطهما من Environment.
             * وإذا لم يوجد AWS_URL نستخدم endpoint + bucket + key.
             */
            $b2Url = env('AWS_URL');

            if (!$b2Url) {
                $b2Url = rtrim(
                    env('B2_ENDPOINT', ''),
                    '/'
                ) . '/' . ltrim(
                    env('B2_BUCKET', ''),
                    '/'
                );
            }

            $fileUrl = rtrim($b2Url, '/') . '/' . ltrim($fileKey, '/');

            /*
             * حفظ بيانات المستند في قاعدة البيانات.
             */
            $document = Document::create([
                'title' => $request->input('title') ?: $originalName,
                'category' => $request->input('category') ?: 'other',

                'case_id' => $request->input('case_id'),
                'hearing_id' => $request->input('hearing_id'),

                'uploader_id' => $user->id,
                'uploader_role' => $user->role ?? 'client',

                'file_name' => $originalName,
                'file_key' => $fileKey,
                'file_url' => $fileUrl,

                'mime_type' => $file->getMimeType()
                    ?: 'application/octet-stream',

                'size_bytes' => $file->getSize(),
            ]);

            return response()->json([
                'message' => 'تم رفع الملف بنجاح',
                'document' => $document,
            ], 201);

        } catch (Throwable $e) {

            /*
             * إظهار السبب الحقيقي بدل رسالة 500 عامة.
             */
            return response()->json([
                'message' => 'فشل رفع الملف',
                'error' => $e->getMessage(),
                'type' => get_class($e),
            ], 500);
        }
    }

    public function download(Request $request, $id)
    {
        try {
            $document = Document::findOrFail($id);

            if (!Storage::disk('b2')->exists($document->file_key)) {
                return response()->json([
                    'message' => 'الملف غير موجود في التخزين.',
                ], 404);
            }

            return Storage::disk('b2')->download(
                $document->file_key,
                $document->file_name
            );

        } catch (Throwable $e) {

            return response()->json([
                'message' => 'تعذر تحميل الملف.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $document = Document::findOrFail($id);

            if ((int) $document->uploader_id !== (int) $request->user()->id) {
                return response()->json([
                    'message' => 'غير مصرح لك بحذف هذا الملف.',
                ], 403);
            }

            Storage::disk('b2')->delete($document->file_key);

            $document->delete();

            return response()->json([
                'message' => 'تم حذف الملف بنجاح.',
            ]);

        } catch (Throwable $e) {

            return response()->json([
                'message' => 'تعذر حذف الملف.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
```
