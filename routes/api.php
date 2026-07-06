<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\MasterRefreshController;
use App\Http\Controllers\Admin\UsersController as AdminUsersController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Fx\BarDataController;
use App\Http\Controllers\Fx\CountryController;
use App\Http\Controllers\Fx\EconomicIndicatorController;
use App\Http\Controllers\Fx\MasterListController;
use App\Http\Controllers\Fx\SummerTimeController;
use App\Http\Controllers\Fx\SymbolController;
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

        // FX - Symbol
        Route::prefix('fx/symbol')->group(function (): void {
            Route::get('/currency-pair-list', [SymbolController::class, 'currencyPairList']);
            Route::get('/currency-index-list', [SymbolController::class, 'currencyIndexList']);
            Route::post('/search', [SymbolController::class, 'search']);
            Route::post('', [SymbolController::class, 'add']);
            Route::get('/{symbol}', [SymbolController::class, 'get']);
            Route::put('/{symbol}', [SymbolController::class, 'update']);
        });

        // FX - Country
        Route::prefix('fx/country')->group(function (): void {
            Route::post('/search', [CountryController::class, 'search']);
            Route::post('', [CountryController::class, 'add']);
            Route::get('/{code}', [CountryController::class, 'get']);
            Route::put('/{code}', [CountryController::class, 'update']);
        });

        // FX - Summer Time
        Route::prefix('fx/summer-time')->group(function (): void {
            Route::post('/search', [SummerTimeController::class, 'search']);
            Route::post('', [SummerTimeController::class, 'add']);
            Route::get('/{targetYear}', [SummerTimeController::class, 'get']);
            Route::put('/{targetYear}', [SummerTimeController::class, 'update']);
        });

        // FX - Economic Indicator
        Route::prefix('fx/economic-indicator')->group(function (): void {
            Route::post('/search', [EconomicIndicatorController::class, 'search']);
            Route::post('', [EconomicIndicatorController::class, 'add']);
            Route::get('/{countryCode}/{code}', [EconomicIndicatorController::class, 'get']);
            Route::put('/{countryCode}/{code}', [EconomicIndicatorController::class, 'update']);
        });

        // FX - Bar Data
        Route::prefix('fx/bar-data')->group(function (): void {
            Route::post('', [BarDataController::class, 'search']);
            Route::post('/import-csv/{symbol}/{barType}/{skipLatest}', [BarDataController::class, 'importCsv']);
            Route::get('/{symbolType}/{barType}', [BarDataController::class, 'status']);
        });
    });

    // 管理者のみ
    Route::middleware(['jwt.auth', 'role.admin'])->prefix('admin')->group(function (): void {
        Route::post('/users', [AdminUsersController::class, 'search']);
        Route::put('/users/approved/{userId}', [AdminUsersController::class, 'approved']);
        Route::put('/users/block/{userId}', [AdminUsersController::class, 'block']);
        Route::put('/users/admin/{userId}', [AdminUsersController::class, 'grantAdmin']);

        Route::get('/master-refresh', [MasterRefreshController::class, 'status']);
        Route::put('/master-refresh', [MasterRefreshController::class, 'refresh']);
    });

    // 認証不要 (public)
    Route::prefix('fx/master-list')->group(function (): void {
        Route::get('/symbol/{symbolType}', [MasterListController::class, 'symbolList']);
        Route::get('/country', [MasterListController::class, 'countryList']);
        Route::get('/currency-pair', [MasterListController::class, 'currencyPairList']);
        Route::get('/currency-index', [MasterListController::class, 'currencyIndexList']);
        Route::get('/economic-indicator/{countryCode}', [MasterListController::class, 'economicIndicatorList']);
    });
});
