<?php

use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\StorefrontAnalyticsController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CredentialController;
use App\Http\Controllers\Api\CreditController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\CustomFieldController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DiscordInteractionController;
use App\Http\Controllers\Api\DiscordSettingsController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\ImportController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\InventoryMediaController;
use App\Http\Controllers\Api\InventoryTimelineController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PublicMediaController;
use App\Http\Controllers\Api\PublicStorefrontController;
use App\Http\Controllers\Api\ReservationController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\SensitiveAccessController;
use App\Http\Controllers\Api\ShopController;
use App\Http\Controllers\Api\TeamController;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/discord/interactions', DiscordInteractionController::class)->middleware('throttle:120,1');
    Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:6,1');
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    // Public plan catalogue for the marketing site (no auth).
    Route::get('/public/plans', [PaymentController::class, 'publicPlans'])->middleware('throttle:60,1');

    // Current legal-document versions (no auth) — the register screen shows these.
    Route::get('/public/legal', fn () => response()->json(['data' => [
        'terms_version' => config('legal.terms_version'),
        'privacy_version' => config('legal.privacy_version'),
        'effective_date' => config('legal.effective_date'),
    ]]))->middleware('throttle:60,1');

    // Public shop storefront (no auth) — only shops that opted in, only their
    // "available" inventory. Images stream through PublicMediaController.
    Route::middleware('throttle:60,1')->group(function () {
        Route::get('/public/listings', [PublicStorefrontController::class, 'listings']);
        Route::get('/public/shops/{shop:slug}', [PublicStorefrontController::class, 'show']);
        Route::get('/public/shops/{shop:slug}/inventory', [PublicStorefrontController::class, 'inventory']);
        Route::get('/public/shops/{shop:slug}/items/{tag}', [PublicStorefrontController::class, 'item']);
        Route::get('/public/shops/{shop:slug}/{target}', [PublicStorefrontController::class, 'branding'])->whereIn('target', ['logo', 'banner']);
        Route::get('/public/media/{media}', [PublicMediaController::class, 'show']);
    });

    // The verification link is opened straight from an email, in any browser or
    // device, so it must NOT require an authenticated session. It is protected
    // by the signed URL (APP_KEY) plus the per-user email hash.
    Route::get('/email/verify/{id}/{hash}', function (string $id, string $hash) {
        $user = User::findOrFail($id);
        abort_unless(hash_equals(sha1($user->getEmailForVerification()), (string) $hash), 403, 'ลิงก์ยืนยันไม่ถูกต้อง');

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        return redirect()->away(rtrim(config('app.frontend_url'), '/').'/verify-email?verified=1');
    })->middleware(['signed', 'throttle:6,1'])->name('verification.verify');

    Route::get('/email/verify', fn () => redirect()->away(rtrim(config('app.frontend_url'), '/').'/verify-email'))
        ->name('verification.notice');

    // Safety net: framework auth middleware redirects unauthenticated browser
    // requests to route('login'); send them to the SPA instead of a 500.
    Route::get('/login', fn () => redirect()->away(rtrim(config('app.frontend_url'), '/').'/login'))
        ->name('login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/sessions', [AuthController::class, 'sessions']);
        Route::delete('/auth/sessions/{session}', [AuthController::class, 'revokeSession']);
        // Re-consent endpoint: outside `verified` + `terms.current` so a user
        // who is behind on the Terms can always accept the new version.
        Route::post('/terms/accept', [AuthController::class, 'acceptTerms']);
        Route::post('/email/verification-notification', function (Request $request) {
            $request->user()->sendEmailVerificationNotification();

            return response()->json(['message' => 'ส่งอีเมลยืนยันแล้ว']);
        })->middleware('throttle:6,1');
        Route::post('/security/2fa/begin', [SensitiveAccessController::class, 'beginTwoFactor']);
        Route::post('/security/2fa/confirm', [SensitiveAccessController::class, 'confirmTwoFactor']);
        Route::post('/security/reauth', [SensitiveAccessController::class, 'confirmReauth'])->middleware('throttle:5,1');

        Route::middleware(['verified', 'terms.current'])->group(function () {
            Route::get('/dashboard', DashboardController::class);
            Route::get('/shop', [ShopController::class, 'show']);
            // Outside shop.writable so the guide can be dismissed even after the trial lapses.
            Route::put('/onboarding/dismiss', [ShopController::class, 'dismissOnboarding']);
            Route::get('/inventory', [InventoryController::class, 'index']);
            Route::get('/inventory/{inventory}', [InventoryController::class, 'show']);
            Route::patch('/inventory/{inventory}/note', [InventoryController::class, 'updateNote'])->middleware('shop.writable');
            Route::get('/inventory/{inventory}/timeline', InventoryTimelineController::class);
            Route::get('/inventory/{inventory}/media', [InventoryMediaController::class, 'index']);
            Route::get('/media/{media}', [InventoryMediaController::class, 'show'])->middleware('signed:relative')->name('api.media.show');
            Route::get('/custom-fields', [CustomFieldController::class, 'index']);
            Route::get('/customers', [CustomerController::class, 'index'])->middleware('shop.permission:inventory.sell');
            Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->middleware('shop.permission:team.manage');
            Route::get('/sales', [SaleController::class, 'index'])->middleware('shop.permission:inventory.sell');
            Route::get('/sales/{sale}', [SaleController::class, 'show'])->middleware('shop.permission:inventory.sell');

            Route::middleware(['shop.writable', 'shop.permission:inventory.manage'])->group(function () {
                Route::post('/inventory', [InventoryController::class, 'store']);
                Route::put('/inventory/{inventory}', [InventoryController::class, 'update']);
                Route::delete('/inventory/{inventory}', [InventoryController::class, 'destroy']);
                Route::post('/inventory/{inventory}/media', [InventoryMediaController::class, 'store']);
                Route::delete('/inventory/{inventory}/media/{media}', [InventoryMediaController::class, 'destroy']);
                Route::post('/custom-fields', [CustomFieldController::class, 'store']);
                Route::put('/custom-fields/{field}', [CustomFieldController::class, 'update']);
                Route::delete('/custom-fields/{field}', [CustomFieldController::class, 'destroy']);
                Route::middleware('plan.feature:bulk_import')->group(function () {
                    Route::post('/imports/preview', [ImportController::class, 'preview']);
                    Route::post('/imports/{import}/confirm', [ImportController::class, 'confirm']);
                });
            });
            Route::get('/imports/template', [ImportController::class, 'template'])->middleware('shop.permission:inventory.manage');
            Route::get('/imports/{import}', [ImportController::class, 'show'])->middleware('shop.permission:inventory.manage');

            Route::middleware(['shop.writable', 'shop.permission:inventory.sell'])->group(function () {
                Route::post('/inventory/{inventory}/reserve', [ReservationController::class, 'store']);
                Route::delete('/inventory/{inventory}/reserve', [ReservationController::class, 'release']);
                Route::post('/inventory/{inventory}/sell', [SaleController::class, 'store']);
            });

            Route::get('/inventory/{inventory}/credentials', [CredentialController::class, 'reveal'])
                ->middleware(['shop.permission:credentials.reveal', 'sensitive', 'throttle:10,1']);

            Route::get('/activity', [ActivityController::class, 'index'])->middleware(['shop.permission:team.manage', 'plan.feature:activity_log']);
            Route::get('/storefront/views', [StorefrontAnalyticsController::class, 'views'])->middleware('plan.feature:analytics');
            Route::get('/team', [TeamController::class, 'index'])->middleware('shop.permission:team.manage');
            Route::post('/team', [TeamController::class, 'store'])->middleware(['shop.writable', 'shop.permission:team.manage']);
            Route::put('/team/{member}', [TeamController::class, 'update'])->middleware(['shop.writable', 'shop.permission:team.manage']);
            Route::put('/team/{member}/password', [TeamController::class, 'resetPassword'])->middleware(['shop.writable', 'shop.permission:team.manage']);
            Route::delete('/team/{member}', [TeamController::class, 'destroy'])->middleware(['shop.writable', 'shop.permission:team.manage']);
            Route::put('/shop', [ShopController::class, 'update'])->middleware(['shop.writable', 'shop.permission:team.manage']);
            Route::post('/shop/branding', [ShopController::class, 'updateBranding'])->middleware(['shop.writable', 'shop.permission:team.manage']);
            Route::delete('/shop/branding', [ShopController::class, 'deleteBranding'])->middleware(['shop.writable', 'shop.permission:team.manage']);

            Route::get('/plans', [PaymentController::class, 'plans']);
            Route::get('/credits', [CreditController::class, 'index'])->middleware('shop.permission:billing.manage');
            Route::get('/billing/history', [CreditController::class, 'history'])->middleware('shop.permission:billing.manage');
            Route::post('/credits/top-ups', [CreditController::class, 'topUp'])->middleware('shop.permission:billing.manage');
            Route::post('/subscriptions/purchase', [CreditController::class, 'purchase'])->middleware('shop.permission:billing.manage');
            Route::put('/subscriptions/auto-renew', [CreditController::class, 'updateAutoRenew'])->middleware('shop.permission:billing.manage');
            Route::get('/export/inventory.csv', [ExportController::class, 'inventory'])->middleware('shop.permission:data.export');
            Route::get('/export/sales.csv', [ExportController::class, 'sales'])->middleware(['shop.permission:data.export', 'plan.feature:advanced_export']);

            Route::middleware('plan.feature:discord')->group(function () {
                Route::get('/discord/settings', [DiscordSettingsController::class, 'show']);
                Route::post('/discord/link-code', [DiscordSettingsController::class, 'linkCode'])->middleware('throttle:10,1');
                Route::middleware(['shop.writable', 'shop.permission:discord.manage'])->group(function () {
                    Route::post('/discord/setup-code', [DiscordSettingsController::class, 'setupCode'])->middleware('throttle:5,1');
                    Route::post('/discord/demo-connect', [DiscordSettingsController::class, 'demoConnect'])->middleware('throttle:5,1');
                    Route::post('/discord/channels/auto-create', [DiscordSettingsController::class, 'autoCreateChannels'])->middleware('throttle:5,1');
                    Route::put('/discord/channels', [DiscordSettingsController::class, 'updateChannels']);
                    Route::post('/discord/test-notification', [DiscordSettingsController::class, 'testNotification'])->middleware('throttle:10,1');
                    Route::delete('/discord/disconnect', [DiscordSettingsController::class, 'disconnect'])->middleware('throttle:5,1');
                });
            });
        });
    });
});
