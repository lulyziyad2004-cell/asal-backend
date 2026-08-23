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
    | Detect uploaded file
    |--------------------------------------------------------------------------
    | بعض البيئات قد لا تجعل hasFile('file') يعمل كما نتوقع،
    | لذلك نفحص allFiles() أيضاً.
    */

    $uploadedFile = $request->file('file');

    if (!$uploadedFile) {
        $allFiles = $request->allFiles();

        if (!empty($allFiles)) {
            $first = reset($allFiles);

            if ($first instanceof \Illuminate\Http\UploadedFile) {
                $uploadedFile = $first;
            }
        }
    }

    $hasFile = $uploadedFile instanceof \Illuminate\Http\UploadedFile;

    /*
    |--------------------------------------------------------------------------
    | Base64 fallback
    |--------------------------------------------------------------------------
    */

    $rawData = $request->input('data');
    $hasBase64 = is_string($rawData) && trim($rawData) !== '';

    if (!$hasFile && !$hasBase64) {
        return response()->json([
            'message' => 'لم يتم استقبال الملف من الطلب',
            'error' => 'No uploaded file or base64 data detected',
            'received_fields' => array_keys($request->all()),
            'received_files' => array_keys($request->allFiles()),
        ], 422);
    }

    /*
    |--------------------------------------------------------------------------
    | Basic data
    |--------------------------------------------------------------------------
    */

    $fileName = 'file';
    $mimeType = 'application/octet-stream';
    $sizeBytes = 0;

    if ($hasFile) {
        $fileName = $uploadedFile->getClientOriginalName() ?: 'file';

        $mimeType =
            $uploadedFile->getClientMimeType()
            ?: $uploadedFile->getMimeType()
            ?: 'application/octet-stream';

        $sizeBytes = $uploadedFile->getSize() ?: 0;
    } else {
        $fileName = basename(
            (string) $request->input('file_name', 'file')
        );

        $mimeType =
            $request->input('mime_type')
            ?: $this->extractMimeTypeFromData($rawData)
            ?: 'application/octet-stream';
    }

    if ($fileName === '' || $fileName === '.') {
        $fileName = 'file';
    }

    $title = trim(
        (string) $request->input(
            'title',
            $fileName
        )
    );

    if ($title === '') {
        $title = $fileName;
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
    |--------------------------------------------------------------------------
    | Validate IDs
    |--------------------------------------------------------------------------
    */

    $validator = Validator::make($request->all(), [
        'case_id' => 'nullable|integer|exists:cases,id',
        'hearing_id' => 'nullable|integer|exists:hearings,id',
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
    |--------------------------------------------------------------------------
    | Store file
    |--------------------------------------------------------------------------
    */

    $fileKey = null;

    try {

        $sanitizedName = $this->sanitizeFileName($fileName);

        $directory = 'documents/' . date('Y/m');

        $storedName =
            Str::random(16) . '_' . $sanitizedName;

        /*
        |----------------------------------------------------------------------
        | Real multipart file
        |----------------------------------------------------------------------
        */

        if ($hasFile) {

            if (!$uploadedFile->isValid()) {
                return response()->json([
                    'message' => 'الملف غير صالح أو لم يتم رفعه بشكل صحيح'
                ], 422);
            }

            /*
             * 50 MB maximum
             */
            if ($uploadedFile->getSize() > 50 * 1024 * 1024) {
                return response()->json([
                    'message' => 'حجم الملف يتجاوز الحد المسموح وهو 50 ميجابايت'
                ], 422);
            }

            $fileKey = Storage::disk('b2')->putFileAs(
                $directory,
                $uploadedFile,
                $storedName
            );

        }

        /*
        |----------------------------------------------------------------------
        | Base64
        |----------------------------------------------------------------------
        */

        else {

            $decodedData = $this->decodeBase64File($rawData);

            if ($decodedData === false) {
                return response()->json([
                    'message' => 'بيانات الملف غير صالحة',
                    'error' => 'Invalid base64 file data'
                ], 422);
            }

            $sizeBytes = strlen($decodedData);

            if ($sizeBytes > 50 * 1024 * 1024) {
                return response()->json([
                    'message' => 'حجم الملف يتجاوز الحد المسموح وهو 50 ميجابايت'
                ], 422);
            }

            $fileKey =
                $directory .
                '/' .
                $storedName;

            $stored = Storage::disk('b2')->put(
                $fileKey,
                $decodedData
            );

            if (!$stored) {
                return response()->json([
                    'message' => 'فشل حفظ الملف في التخزين'
                ], 500);
            }
        }

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

    /*
    |--------------------------------------------------------------------------
    | Database record
    |--------------------------------------------------------------------------
    */

    try {

        $document = Document::create([
            'title' => $title,
            'category' => $category,
            'case_id' => $request->input('case_id'),
            'hearing_id' => $request->input('hearing_id'),
            'uploader_id' => $user->id,
            'uploader_role' => $user->role,
            'file_name' => $fileName,
            'file_key' => $fileKey,
            'file_url' => null,
            'mime_type' => $mimeType,
            'size_bytes' => $sizeBytes,
        ]);

    } catch (\Throwable $e) {

        try {
            Storage::disk('b2')->delete($fileKey);
        } catch (\Throwable $ignored) {
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
    }

    /*
    |--------------------------------------------------------------------------
    | Audit
    |--------------------------------------------------------------------------
    */

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

    } catch (\Throwable $e) {
    }

    return response()->json([
        'message' => 'تم رفع المستند وحفظه بنجاح',
        'id' => $document->id,
        'document' => $document,
    ], 201);
}
