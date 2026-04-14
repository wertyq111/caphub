<?php

use App\Http\Controllers\Admin\AiInvocationController;
use App\Http\Controllers\Admin\Auth\LoginController;
use App\Http\Controllers\Admin\GlossaryController;
use App\Http\Controllers\Admin\TranslationJobController;
use App\Http\Controllers\Admin\TranslationProviderController;
use App\Http\Controllers\Demo\AsyncTranslationController;
use App\Http\Controllers\Demo\DashboardStatsController;
use App\Http\Controllers\Demo\HermesChatController;
use App\Http\Controllers\Demo\ShowTranslationJobController;
use App\Http\Controllers\Demo\ShowTranslationResultController;
use App\Http\Controllers\Demo\SyncTranslationController;
use Illuminate\Support\Facades\Route;

Route::get('/ping', function () {
    return response()->json(['ok' => true]);
});

Route::get('/demo/dashboard/stats', DashboardStatsController::class);

Route::post('/demo/chat', HermesChatController::class)
    ->middleware('throttle:demo-sync-translation');

Route::post('/demo/translate/sync', SyncTranslationController::class)
    ->middleware('throttle:demo-sync-translation');

Route::prefix('demo/translate')->group(function (): void {
    Route::post('/async', AsyncTranslationController::class);
    Route::get('/jobs/{jobUuid}', ShowTranslationJobController::class);
    Route::get('/jobs/{jobUuid}/result', ShowTranslationResultController::class);
});

Route::prefix('admin')->group(function (): void {
    Route::post('/login', LoginController::class);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::apiResource('glossaries', GlossaryController::class)
            ->only(['index', 'store', 'update', 'destroy']);
        Route::get('/translation-jobs', [TranslationJobController::class, 'index']);
        Route::get('/translation-jobs/{job}', [TranslationJobController::class, 'show']);
        Route::get('/ai-invocations', [AiInvocationController::class, 'index']);
        Route::get('/system/translation-provider', [TranslationProviderController::class, 'show']);
        Route::put('/system/translation-provider', [TranslationProviderController::class, 'update']);
    });
});
