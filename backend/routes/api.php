<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CredentialController;
use App\Http\Controllers\Api\CustomFieldController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\ImportController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\InventoryMediaController;
use App\Http\Controllers\Api\InventoryTimelineController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ReservationController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\SensitiveAccessController;
use App\Http\Controllers\Api\TeamController;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:6,1');
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/sessions', [AuthController::class, 'sessions']);
        Route::delete('/auth/sessions/{session}', [AuthController::class, 'revokeSession']);
        Route::post('/email/verification-notification', function (Request $request) {
            $request->user()->sendEmailVerificationNotification();

            return response()->json(['message' => 'ส่งอีเมลยืนยันแล้ว']);
        })->middleware('throttle:6,1');
        Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
            $request->fulfill();

            return response()->json(['message' => 'ยืนยันอีเมลแล้ว']);
        })->middleware('signed')->name('verification.verify');
        Route::post('/security/2fa/begin', [SensitiveAccessController::class, 'beginTwoFactor']);
        Route::post('/security/2fa/confirm', [SensitiveAccessController::class, 'confirmTwoFactor']);
        Route::post('/security/reauth', [SensitiveAccessController::class, 'confirmReauth'])->middleware('throttle:5,1');

        Route::middleware('verified')->group(function () {
            Route::get('/dashboard', DashboardController::class);
            Route::get('/inventory', [InventoryController::class, 'index']);
            Route::get('/inventory/{inventory}', [InventoryController::class, 'show']);
            Route::get('/inventory/{inventory}/timeline', InventoryTimelineController::class);
            Route::get('/inventory/{inventory}/media', [InventoryMediaController::class, 'index']);
            Route::get('/media/{media}', [InventoryMediaController::class, 'show'])->middleware('signed')->name('api.media.show');
            Route::get('/custom-fields', [CustomFieldController::class, 'index']);

            Route::middleware(['shop.writable', 'shop.permission:inventory.manage'])->group(function () {
                Route::post('/inventory', [InventoryController::class, 'store']);
                Route::put('/inventory/{inventory}', [InventoryController::class, 'update']);
                Route::delete('/inventory/{inventory}', [InventoryController::class, 'destroy']);
                Route::post('/inventory/{inventory}/media', [InventoryMediaController::class, 'store']);
                Route::delete('/media/{media}', [InventoryMediaController::class, 'destroy']);
                Route::post('/custom-fields', [CustomFieldController::class, 'store']);
                Route::put('/custom-fields/{field}', [CustomFieldController::class, 'update']);
                Route::delete('/custom-fields/{field}', [CustomFieldController::class, 'destroy']);
                Route::post('/imports/preview', [ImportController::class, 'preview']);
                Route::post('/imports/{import}/confirm', [ImportController::class, 'confirm']);
            });
            Route::get('/imports/{import}', [ImportController::class, 'show'])->middleware('shop.permission:inventory.manage');

            Route::middleware(['shop.writable', 'shop.permission:inventory.sell'])->group(function () {
                Route::post('/inventory/{inventory}/reserve', [ReservationController::class, 'store']);
                Route::delete('/inventory/{inventory}/reserve', [ReservationController::class, 'release']);
                Route::post('/inventory/{inventory}/sell', [SaleController::class, 'store']);
            });

            Route::get('/inventory/{inventory}/credentials', [CredentialController::class, 'reveal'])
                ->middleware(['shop.permission:credentials.reveal', 'sensitive', 'throttle:10,1']);

            Route::get('/team', [TeamController::class, 'index'])->middleware('shop.permission:team.manage');
            Route::post('/team', [TeamController::class, 'store'])->middleware(['shop.writable', 'shop.permission:team.manage']);
            Route::put('/team/{member}', [TeamController::class, 'update'])->middleware(['shop.writable', 'shop.permission:team.manage']);
            Route::delete('/team/{member}', [TeamController::class, 'destroy'])->middleware(['shop.writable', 'shop.permission:team.manage']);

            Route::get('/plans', [PaymentController::class, 'plans']);
            Route::post('/payments', [PaymentController::class, 'store'])->middleware('shop.permission:billing.manage');
            Route::get('/payments/{payment}', [PaymentController::class, 'show'])->middleware('shop.permission:billing.manage');
            Route::get('/export/inventory.csv', ExportController::class)->middleware('shop.permission:data.export');
        });
    });
});
