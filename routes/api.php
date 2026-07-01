<?php

declare(strict_types=1);

use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {

    // 認証が必要なルート
    Route::middleware('jwt.auth')->group(function (): void {
        Route::post('/auth/login', [AuthController::class, 'login']);
        Route::post('/auth/logout-api', [AuthController::class, 'logout']);

        Route::get('/user', [UserController::class, 'profile']);
        Route::post('/user', [UserController::class, 'registration']);
        Route::put('/user/{userId}', [UserController::class, 'update']);
    });

    // 管理者のみ
    Route::middleware(['jwt.auth', 'role.admin'])->prefix('admin')->group(function (): void {
        // Phase 3 で実装
    });

    // 認証不要 (public)
    Route::prefix('fx/master-list')->group(function (): void {
        // Phase 4 で実装
    });
});
