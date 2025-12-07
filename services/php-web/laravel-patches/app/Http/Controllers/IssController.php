<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class IssController extends Controller
{
    private function base(): string
    {
        return getenv('RUST_BASE') ?: 'http://rust_iss:3000';
    }

    public function index()
    {
        $base = $this->base();

        // Fetch latest ISS data with caching
        $latest = Cache::remember('iss_latest', 30, function () use ($base) {
            try {
                $response = @file_get_contents($base . '/api/iss/latest');
                return $response ? json_decode($response, true) : [];
            } catch (\Throwable $e) {
                return [];
            }
        });

        // Fetch trend data
        $trend = Cache::remember('iss_trend', 60, function () use ($base) {
            try {
                $response = @file_get_contents($base . '/api/iss/trend?hours=24');
                return $response ? json_decode($response, true) : [];
            } catch (\Throwable $e) {
                return [];
            }
        });

        // Extract data from response
        $issData = $latest['data'] ?? $latest['payload'] ?? $latest;
        $trendData = $trend['data'] ?? $trend;

        return view('iss', [
            'latest' => $issData,
            'trend' => $trendData,
            'base' => $base,
        ]);
    }
}
