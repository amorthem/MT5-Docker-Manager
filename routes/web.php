<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\DockerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\Api\DockerController as ApiDockerController;
use App\Http\Controllers\Api\MetricsController;
use Inertia\Inertia;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
});

Route::middleware([
    'auth',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::prefix('docker-containers')->name('docker.containers.')->group(function () {
        Route::get('/', [DockerController::class, 'index'])->name('index');
        Route::get('/{container}', [DockerController::class, 'show'])->name('show');
    });

    Route::prefix('dashboard/data')->name('dashboard.data.')->group(function () {
        Route::get('/containers/overview', [ApiDockerController::class, 'overview'])->name('containers.overview');
        Route::get('/metrics/host', [MetricsController::class, 'host'])->name('metrics.host');
    });

    Route::prefix('docker-containers/data')->name('docker.data.')->group(function () {
        Route::get('/overview', [ApiDockerController::class, 'overview'])->name('overview');
        Route::get('/{container}', [ApiDockerController::class, 'show'])->name('show');
        Route::get('/{container}/logs', [ApiDockerController::class, 'logs'])->name('logs');
        Route::get('/{container}/metrics', [ApiDockerController::class, 'metrics'])->name('metrics');
        Route::post('/{container}/{action}', [ApiDockerController::class, 'action'])
            ->whereIn('action', ['start', 'stop', 'restart'])
            ->middleware('role:support,admin,dev')
            ->name('action');
        Route::delete('/{container}', [ApiDockerController::class, 'remove'])
            ->middleware('role:dev')
            ->name('remove');
        Route::get('/images/list', [ApiDockerController::class, 'images'])
            ->middleware('role:dev')
            ->name('images');
        Route::post('/', [ApiDockerController::class, 'create'])
            ->middleware('role:dev')
            ->name('create');
    });

    Route::middleware('role:admin,dev')->group(function () {
        Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
        Route::post('/users', [UserManagementController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [UserManagementController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserManagementController::class, 'destroy'])->name('users.destroy');
    });
});
