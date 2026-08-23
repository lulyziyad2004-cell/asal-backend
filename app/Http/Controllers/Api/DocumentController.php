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
    /**
     * عرض المستندات.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $documents = Document::query()
            ->where('uploader_id', $user->id)
            ->latest('created_at')
            ->get();

        return response()->json($documents);
    }

    /**
     * رفع مستند جديد.
     *
     * POST /api/upload
     */
    public function store(Request $request)
    {
        try {
            if (!$request->hasFile('file')) {
                return response()->json([
                    'message' => 'لم يتم إرسال ملف.',
                    'error' => 'file_is_required',
                ], 422);
            }

            $file = $request->file('file');

            if (!$file->isValid()) {
                return response()->json([
                    'message' => 'الملف المرسل غير صالح.',
                    'error' => 'invalid_file',
                ], 422);
            }

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
                    'in:contract,memo,poa,hearing_related,other',
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

            /*
             * التخزين على public.
             * لا نستخدم base64 ولا نضع Content-Type يدويًا.
             */
            $fileKey = 'documents/' . $safeName;

            $stored = Storage::disk('public')->putFileAs(
                'documents',
                $file,
                $safeName
            );

            if (!$stored) {
                return response()->json([
                    'message' => 'تعذر حفظ الملف في التخزين.',
                    'error' => 'storage_failed',
                ], 500);
            }

            $fileUrl = Storage::disk('public')->url($fileKey);

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
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
            ]);

            return response()->json([
                'message' => 'تم رفع الملف بنجاح',
                'document' => $document,
            ], 201);

        } catch (Throwable $e) {

            return response()->json([
                'message' => 'فشل رفع الملف.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * تحميل مستند.
     */
    public function download(Request $request, $id)
    {
        try {
            $document = Document::findOrFail($id);

            if (!Storage::disk('public')->exists($document->file_key)) {
                return response()->json([
                    'message' => 'الملف غير موجود في التخزين.',
                ], 404);
            }

            return Storage::disk('public')->download(
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

    /**
     * حذف مستند.
     */
    public function destroy(Request $request, $id)
    {
        try {
            $document = Document::findOrFail($id);

            if ($document->uploader_id !== $request->user()->id) {
                return response()->json([
                    'message' => 'غير مصرح لك بحذف هذا الملف.',
                ], 403);
            }

            Storage::disk('public')->delete($document->file_key);

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
