<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OsdrController;
use App\Http\Controllers\IssController;
use App\Http\Controllers\ProxyController;
use App\Http\Controllers\AstroController;
use App\Http\Controllers\CmsController;
use App\Http\Controllers\SpaceController;

// Главная -> Dashboard
Route::get('/', fn() => redirect('/dashboard'));

// Основные панели
Route::get('/dashboard', [DashboardController::class, 'index']);
Route::get('/iss',       [IssController::class, 'index']);
Route::get('/osdr',      [OsdrController::class, 'index']);

// Space Data страницы
Route::get('/apod',   [SpaceController::class, 'apod']);
Route::get('/neo',    [SpaceController::class, 'neo']);
Route::get('/donki',  [SpaceController::class, 'donki']);
Route::get('/spacex', [SpaceController::class, 'spacex']);

// Прокси к Rust backend
Route::prefix('api/iss')->group(function () {
    Route::get('/latest', [ProxyController::class, 'latest']);
    Route::get('/trend',  [ProxyController::class, 'trend']);
    Route::get('/last',   [ProxyController::class, 'last']); 
});

Route::prefix('api/osdr')->group(function () {
    Route::get('/', [ProxyController::class, 'osdrList']);
});

Route::prefix('api/space')->group(function () {
    Route::get('/cache/{source}', [ProxyController::class, 'spaceCache']);
});

// JWST галерея (JSON)
Route::get('/api/jwst/feed', [DashboardController::class, 'jwstFeed']);

// Астрономические события
Route::get('/api/astro/events', [AstroController::class, 'events']);

// CMS страницы
Route::get('/page/{slug}', [CmsController::class, 'page'])
    ->where('slug', '[a-z0-9\-]+');

// Health endpoint
Route::get('/health', fn() => response()->json([
    'ok' => true,
    'service' => 'php-web',
    'timestamp' => now()->toIso8601String(),
]));
