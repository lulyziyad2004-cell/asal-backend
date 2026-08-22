<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CaseController;
use App\Http\Controllers\Api\HearingController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\AdminController;

/*
|--------------------------------------------------------------------------
| CORS Preflight
|--------------------------------------------------------------------------
*/

Route::options('/{any}', function (Request $request) {
    $origin = $request->header('Origin');

    $allowedOrigins = [
        'https://asal-final.vercel.app',
        'https://asal-frontend-coral.vercel.app',
    ];

    $headers = [
        'Access-Control-Allow-Methods' => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
        'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With, Accept, Origin',
        'Access-Control-Allow-Credentials' => 'true',
    ];

    if ($origin && in_array($origin, $allowedOrigins, true)) {
        $headers['Access-Control-Allow-Origin'] = $origin;
    }

    return response('', 200, $headers);
})->where('any', '.*');

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::get('/subscriptions/plans', [SubscriptionController::class, 'plans']);

/*
|--------------------------------------------------------------------------
| Protected Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */

    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::post('/auth/set-password', [AuthController::class, 'setPassword'])
        ->middleware('admin');

    /*
    |--------------------------------------------------------------------------
    | Statistics
    |--------------------------------------------------------------------------
    |
    | The frontend requests:
    | GET /api/stats
    |
    */

    Route::get('/stats', [AdminController::class, 'stats']);

    /*
    |--------------------------------------------------------------------------
    | Cases
    |--------------------------------------------------------------------------
    */

    Route::apiResource('cases', CaseController::class);

    /*
    |--------------------------------------------------------------------------
    | Hearings
    |--------------------------------------------------------------------------
    */

    Route::apiResource('hearings', HearingController::class);

    /*
    |--------------------------------------------------------------------------
    | Invoices
    |--------------------------------------------------------------------------
    */

    Route::apiResource('invoices', InvoiceController::class);

    Route::post(
        '/invoices/{id}/cancel',
        [InvoiceController::class, 'cancel']
    );

    Route::post(
        '/invoices/{id}/refund',
        [InvoiceController::class, 'refund']
    )->middleware('admin');

    /*
    |--------------------------------------------------------------------------
    | Payments
    |--------------------------------------------------------------------------
    */

    Route::post(
        '/payments/create-session',
        [PaymentController::class, 'createSession']
    );

    Route::get(
        '/payments/status/{invoiceId}',
        [PaymentController::class, 'status']
    );

    Route::post(
        '/payments/callback',
        [PaymentController::class, 'callback']
    )->withoutMiddleware('auth:sanctum');

    /*
    |--------------------------------------------------------------------------
    | Documents
    |--------------------------------------------------------------------------
    */

    Route::apiResource('documents', DocumentController::class)
        ->only([
            'index',
            'store',
            'destroy',
        ]);

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/notifications',
        [NotificationController::class, 'index']
    );

    Route::post(
        '/notifications/{id}/mark-read',
        [NotificationController::class, 'markRead']
    );

    Route::delete(
        '/notifications/{id}',
        [NotificationController::class, 'destroy']
    );

    /*
    |--------------------------------------------------------------------------
    | Messages
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/messages/thread/{peerId}',
        [MessageController::class, 'thread']
    );

    Route::post(
        '/messages/send',
        [MessageController::class, 'send']
    );

    Route::get(
        '/messages/contacts',
        [MessageController::class, 'contacts']
    );

    /*
    |--------------------------------------------------------------------------
    | Subscriptions
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/subscriptions/mine',
        [SubscriptionController::class, 'mine']
    );

    Route::get(
        '/subscriptions/my-records',
        [SubscriptionController::class, 'myRecords']
    );

    Route::post(
        '/subscriptions/upgrade',
        [SubscriptionController::class, 'upgrade']
    );

    Route::post(
        '/subscriptions/{id}/cancel',
        [SubscriptionController::class, 'cancel']
    );

    /*
    |--------------------------------------------------------------------------
    | Admin Routes
    |--------------------------------------------------------------------------
    */

    Route::middleware('admin')->group(function () {

        Route::get(
            '/admin/stats',
            [AdminController::class, 'stats']
        );

        Route::get(
            '/admin/users',
            [AdminController::class, 'users']
        );

        Route::post(
            '/admin/users/{id}/disable',
            [AdminController::class, 'disableUser']
        );

        Route::post(
            '/admin/users/{id}/set-role',
            [AdminController::class, 'setUserRole']
        );

        Route::post(
            '/admin/users/{id}/suspend',
            [AdminController::class, 'suspendUser']
        );

        Route::delete(
            '/admin/users/{id}',
            [AdminController::class, 'deleteUser']
        );

        Route::get(
            '/admin/audit-logs',
            [AdminController::class, 'auditLogs']
        );

        Route::get(
            '/admin/transactions',
            [AdminController::class, 'transactions']
        );

        Route::get(
            '/admin/subscriptions',
            [AdminController::class, 'subscriptions']
        );
    });
});
