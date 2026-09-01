<?php

use App\Http\Controllers\AdminController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $adminId = session()->get('admin_user_id');

    if ($adminId && User::whereKey($adminId)->where('is_super_admin', true)->exists()) {
        return redirect()->route('admin.dashboard');
    }

    session()->forget('admin_user_id');

    return redirect()->route('admin.login');
});

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [AdminController::class, 'loginForm'])->name('login');
    Route::post('/login', [AdminController::class, 'login'])->middleware('throttle:5,1');
    Route::middleware('admin.session')->group(function () {
        Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
        Route::get('/shops', [AdminController::class, 'shops'])->name('shops.index');
        Route::get('/shops/create', [AdminController::class, 'createShop'])->name('shops.create');
        Route::post('/shops', [AdminController::class, 'storeShop'])->name('shops.store');
        Route::get('/shops/{shop}', [AdminController::class, 'showShop'])->withTrashed()->name('shops.show');
        Route::get('/shops/{shop}/edit', [AdminController::class, 'editShop'])->withTrashed()->name('shops.edit');
        Route::patch('/shops/{shop}', [AdminController::class, 'updateShop'])->withTrashed()->name('shops.update');
        Route::delete('/shops/{shop}', [AdminController::class, 'destroyShop'])->name('shops.destroy');
        Route::patch('/shops/{shop}/restore', [AdminController::class, 'restoreShop'])->withTrashed()->name('shops.restore');
        Route::patch('/shops/{shop}/auto-renew', [AdminController::class, 'updateAutoRenew'])->name('shops.auto-renew');
        Route::get('/plans', [AdminController::class, 'plans'])->name('plans.index');
        Route::get('/plans/create', [AdminController::class, 'createPlan'])->name('plans.create');
        Route::get('/plans/{plan}/edit', [AdminController::class, 'editPlan'])->name('plans.edit');
        Route::get('/top-ups', [AdminController::class, 'topUps'])->name('top-ups.index');
        Route::get('/top-ups/{payment}', [AdminController::class, 'showTopUp'])->name('top-ups.show');
        Route::get('/top-ups/{payment}/slip', [AdminController::class, 'slip'])->name('top-ups.slip');
        Route::get('/logs', [AdminController::class, 'logs'])->name('logs.index');
        Route::post('/plans', [AdminController::class, 'storePlan'])->name('plans.store');
        Route::patch('/plans/{plan}', [AdminController::class, 'updatePlan'])->name('plans.update');
        Route::patch('/top-ups/{payment}', [AdminController::class, 'reviewTopUp'])->name('top-ups.review');
        Route::post('/logout', [AdminController::class, 'logout'])->name('logout');
    });
});
