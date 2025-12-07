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
Route::get('/api/astro/positions', [AstroController::class, 'positions']);
Route::get('/api/astro/debug', [AstroController::class, 'debug']);

// CMS страницы
Route::get('/page/{slug}', [CmsController::class, 'page'])
    ->where('slug', '[a-z0-9\-]+');

Route::get('/health', fn() => view('health'));

// Health API endpoints
Route::get('/api/health/db', function () {
    try {
        \DB::connection()->getPdo();
        $version = \DB::selectOne('SELECT version()');
        return response()->json([
            'ok' => true,
            'version' => $version->version ?? 'unknown'
        ]);
    } catch (\Exception $e) {
        return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
    }
});

Route::get('/api/health/nasa', function () {
    try {
        $key = getenv('NASA_API_KEY') ?: 'DEMO_KEY';
        $response = \Http::timeout(5)->get('https://api.nasa.gov/planetary/apod', [
            'api_key' => $key,
            'thumbs' => 'true'
        ]);
        return response()->json([
            'ok' => $response->successful(),
            'status' => $response->status()
        ]);
    } catch (\Exception $e) {
        return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
    }
});
