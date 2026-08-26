<?php

use App\Http\Controllers\AdminController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json(['name' => 'GamoryID API', 'version' => 'v1', 'status' => 'ok']);
});

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [AdminController::class, 'loginForm'])->name('login');
    Route::post('/login', [AdminController::class, 'login'])->middleware('throttle:5,1');
    Route::middleware('admin.session')->group(function () {
        Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
        Route::patch('/shops/{shop}', [AdminController::class, 'updateShop'])->name('shops.update');
        Route::patch('/plans/{plan}', [AdminController::class, 'updatePlan'])->name('plans.update');
        Route::post('/logout', [AdminController::class, 'logout'])->name('logout');
    });
});
