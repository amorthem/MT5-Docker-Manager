<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\DockerController;
use App\Http\Controllers\Api\MetricsController;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::prefix('containers')->group(function () {
        Route::get('/', [DockerController::class, 'index'])->name('api.containers.index');
        Route::get('/overview', [DockerController::class, 'overview'])->name('api.containers.overview');
        Route::get('/images', [DockerController::class, 'images'])->middleware('role:dev')->name('api.containers.images');
        Route::post('/', [DockerController::class, 'create'])->middleware('role:dev')->name('api.containers.create');
        Route::get('/{container}', [DockerController::class, 'show'])->name('api.containers.show');
        Route::get('/{container}/logs', [DockerController::class, 'logs'])->name('api.containers.logs');
        Route::get('/{container}/metrics', [DockerController::class, 'metrics'])->name('api.containers.metrics');
        Route::post('/{container}/{action}', [DockerController::class, 'action'])
            ->whereIn('action', ['start', 'stop', 'restart'])
            ->middleware('role:support,admin,dev')
            ->name('api.containers.action');
        Route::delete('/{container}', [DockerController::class, 'remove'])
            ->middleware('role:dev')
            ->name('api.containers.remove');
    });

    Route::prefix('metrics')->group(function () {
        Route::get('/host', [MetricsController::class, 'host'])->name('api.metrics.host');
        Route::get('/overview', fn () => response()->json([
            'data' => ['host' => null, 'containers' => [], 'stale' => true],
        ]))->name('api.metrics.overview');
    });
});
