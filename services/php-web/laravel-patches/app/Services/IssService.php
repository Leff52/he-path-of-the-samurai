<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\DTOs\IssDataDto;

class IssService
{
    private const CACHE_TTL = 60; // секунды
    private const RUST_BASE_URL = 'http://rust_iss:3000';
    
    public function getLatestPosition(): ?IssDataDto
    {
        return Cache::remember('iss:latest', self::CACHE_TTL, function () {
            try {
                $response = Http::timeout(10)
                    ->retry(3, 100) // 3 попытки, 100ms между попытками
                    ->get(self::RUST_BASE_URL . '/last');
                
                if ($response->successful()) {
                    $data = $response->json();
                    
                    if (isset($data['ok']) && $data['ok'] === false) {
                        // Unified error format от Rust
                        Log::error('ISS API returned error', [
                            'code' => $data['error']['code'] ?? 'UNKNOWN',
                            'message' => $data['error']['message'] ?? '',
                            'trace_id' => $data['error']['trace_id'] ?? '',
                        ]);
                        return null;
                    }
                    
                    return IssDataDto::fromArray($data);
                }
                
                Log::error('ISS API error', ['status' => $response->status()]);
                return null;
                
            } catch (\Exception $e) {
                Log::error('ISS API exception', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                return null;
            }
        });
    }
    
    public function getTrend(): ?array
    {
        return Cache::remember('iss:trend', self::CACHE_TTL, function () {
            try {
                $response = Http::timeout(10)
                    ->retry(3, 100)
                    ->get(self::RUST_BASE_URL . '/iss/trend');
                
                if ($response->successful()) {
                    return $response->json();
                }
                
                return null;
                
            } catch (\Exception $e) {
                Log::error('ISS trend exception', ['error' => $e->getMessage()]);
                return null;
            }
        });
    }
    
    public function triggerFetch(): bool
    {
        try {
            $response = Http::timeout(15)
                ->get(self::RUST_BASE_URL . '/fetch');
            
            // Инвалидация кэша после принудительного обновления
            Cache::forget('iss:latest');
            Cache::forget('iss:trend');
            
            return $response->successful();
            
        } catch (\Exception $e) {
            Log::error('ISS fetch trigger failed', ['error' => $e->getMessage()]);
            return false;
        }
    }
}
