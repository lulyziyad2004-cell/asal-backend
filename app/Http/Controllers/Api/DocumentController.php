# DocumentController.php

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

        if (!$user) {
            return response()->json([
                'message' => 'غير مصرح. يرجى تسجيل الدخول.',
            ], 401);
        }

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

            $originalName = $file->getClientOriginalName();
            $extension = strtolower($file->getClientOriginalExtension());

            $safeName = Str::uuid()->toString();

            if ($extension !== '') {
                $safeName .= '.' . $extension;
            }

            $fileKey = 'documents/' . $safeName;

            /*
             * رفع الملف إلى Backblaze B2.
             *
             * لا نستخدم exists() بعد الرفع،
             * لأن Bucket خاص وقد تتطلب عملية التحقق صلاحيات إضافية.
             */
            $stored = Storage::disk('b2')->putFileAs(
                'documents',
                $file,
                $safeName
            );

            if (!$stored) {
                return response()->json([
                    'message' => 'تعذر رفع الملف إلى Backblaze B2.',
                    'error' => 'b2_upload_failed',
                ], 500);
            }

            /*
             * حفظ بيانات الملف في قاعدة البيانات.
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

                'file_url' => null,

                'mime_type' => $file->getMimeType()
                    ?: 'application/octet-stream',

                'size_bytes' => $file->getSize(),
            ]);

            /*
             * رابط التحميل يمر عبر Laravel
             * لأن Bucket في Backblaze خاص Private.
             */
            $document->update([
                'file_url' => url(
                    '/api/documents/' . $document->id . '/download'
                ),
            ]);

            return response()->json([
                'message' => 'تم رفع الملف بنجاح',
                'document' => $document->fresh(),
            ], 201);

        } catch (Throwable $e) {

            report($e);

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
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'message' => 'غير مصرح. يرجى تسجيل الدخول.',
                ], 401);
            }

            $document = Document::findOrFail($id);

            /*
             * تحميل الملف مباشرة من B2.
             *
             * لا نستخدم exists() قبل التحميل.
             */
            return Storage::disk('b2')->download(
                $document->file_key,
                $document->file_name,
                [
                    'Content-Type' => $document->mime_type
                        ?: 'application/octet-stream',
                ]
            );

        } catch (Throwable $e) {

            report($e);

            return response()->json([
                'message' => 'تعذر تحميل الملف.',
                'error' => $e->getMessage(),
                'type' => get_class($e),
            ], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'message' => 'غير مصرح. يرجى تسجيل الدخول.',
                ], 401);
            }

            $document = Document::findOrFail($id);

            if ((int) $document->uploader_id !== (int) $user->id) {
                return response()->json([
                    'message' => 'غير مصرح لك بحذف هذا الملف.',
                ], 403);
            }

            Storage::disk('b2')->delete(
                $document->file_key
            );

            $document->delete();

            return response()->json([
                'message' => 'تم حذف الملف بنجاح.',
            ]);

        } catch (Throwable $e) {

            report($e);

            return response()->json([
                'message' => 'تعذر حذف الملف.',
                'error' => $e->getMessage(),
                'type' => get_class($e),
            ], 500);
        }
    }
}
```
