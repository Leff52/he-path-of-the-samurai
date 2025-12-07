<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class AstroController extends Controller
{
    public function debug(Request $r)
    {
        $appId = preg_replace('/\s+/', '', (string) env('ASTRO_APP_ID', ''));
        $appSecret = preg_replace('/\s+/', '', (string) env('ASTRO_APP_SECRET', ''));
        
        $debug = [
            'appId_length' => strlen($appId),
            'appSecret_length' => strlen($appSecret),
            'appId_first8' => substr($appId, 0, 8),
        ];
        
        if (empty($appId) || empty($appSecret)) {
            return response()->json(['error' => 'Missing credentials', 'debug' => $debug]);
        }
        
        $authString = base64_encode("{$appId}:{$appSecret}");
        $fromDate = now('UTC')->toDateString();
        $toDate = now('UTC')->addDays(365)->toDateString();
        
        $response = Http::timeout(15)
            ->withHeaders([
                'User-Agent' => 'php-web/1.0',
                'Accept' => 'application/json',
                'Authorization' => "Basic {$authString}"
            ])
            ->get("https://api.astronomyapi.com/api/v2/bodies/events/sun", [
                'latitude' => 55.7558,
                'longitude' => 37.6176,
                'elevation' => 0,
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'time' => '00:00:00'
            ]);
        
        return response()->json([
            'debug' => $debug,
            'request' => [
                'from_date' => $fromDate,
                'to_date' => $toDate,
            ],
            'response_status' => $response->status(),
            'response_body' => $response->json() ?? $response->body(),
        ]);
    }

    public function events(Request $r)
    {
        $lat  = (float) $r->query('lat', 55.7558);
        $lon  = (float) $r->query('lon', 37.6176);
        $days = max(1, min(365, (int) $r->query('days', 7)));

        $fromDate = now('UTC')->toDateString();
        $toDate   = now('UTC')->addDays($days)->toDateString();


        $cacheKey = "astro_events_{$lat}_{$lon}_{$fromDate}_{$toDate}";
        
        $result = Cache::remember($cacheKey, 3600, function () use ($lat, $lon, $fromDate, $toDate) {
            $appId = preg_replace('/\s+/', '', (string) env('ASTRO_APP_ID', ''));
            $appSecret = preg_replace('/\s+/', '', (string) env('ASTRO_APP_SECRET', ''));

            if (empty($appId) || empty($appSecret)) {
                return $this->getMockEvents($lat, $lon, $fromDate, $toDate);
            }

            $authString = base64_encode("{$appId}:{$appSecret}");

            $allEvents = [
                'data' => [
                    'observer' => [
                        'location' => [
                            'latitude' => $lat,
                            'longitude' => $lon,
                            'elevation' => 0
                        ]
                    ],
                    'dates' => [
                        'from' => $fromDate,
                        'to' => $toDate
                    ],
                    'rows' => []
                ]
            ];
            foreach (['sun', 'moon'] as $body) {
                try {
                    $response = Http::timeout(10)
                        ->withHeaders([
                            'User-Agent' => 'php-web/1.0',
                            'Accept' => 'application/json',
                            'Authorization' => "Basic {$authString}"
                        ])
                        ->get("https://api.astronomyapi.com/api/v2/bodies/events/{$body}", [
                            'latitude' => $lat,
                            'longitude' => $lon,
                            'elevation' => 0,
                            'from_date' => $fromDate,
                            'to_date' => $toDate,
                            'time' => '00:00:00'
                        ]);

                    if ($response->successful()) {
                        $bodyData = $response->json();

                        if (isset($bodyData['data']['table']['rows']) && is_array($bodyData['data']['table']['rows'])) {
                            foreach ($bodyData['data']['table']['rows'] as $row) {

                                $bodyInfo = $row['entry'] ?? ['id' => $body, 'name' => ucfirst($body)];
                                $events = [];
                                
                                if (isset($row['cells']) && is_array($row['cells'])) {
                                    foreach ($row['cells'] as $cell) {
                                        $events[] = $cell;
                                    }
                                }
                                
                                if (!empty($events)) {
                                    $allEvents['data']['rows'][] = [
                                        'body' => $bodyInfo,
                                        'events' => $events
                                    ];
                                }
                            }
                        }
                        //  на всякий случай добавим поддержку старого формата с data.rows
                        elseif (isset($bodyData['data']['rows']) && is_array($bodyData['data']['rows'])) {
                            foreach ($bodyData['data']['rows'] as $row) {
                                $allEvents['data']['rows'][] = $row;
                            }
                        }
                    } else {
                        \Log::warning("AstronomyAPI error for {$body}", [
                            'status' => $response->status(),
                            'body' => $response->body()
                        ]);
                    }

                } catch (\Exception $e) {
                    \Log::error("AstronomyAPI exception for {$body}: " . $e->getMessage());
                }
            }

            return $allEvents;
        });

        return response()->json($result);
    }

    public function positions(Request $r)
    {
        $lat  = (float) $r->query('lat', 55.7558);
        $lon  = (float) $r->query('lon', 37.6176);
        $days = max(1, min(30, (int) $r->query('days', 7)));

        $fromDate = now('UTC')->toDateString();
        $toDate   = now('UTC')->addDays($days)->toDateString();

        $cacheKey = "astro_positions_{$lat}_{$lon}_{$fromDate}_{$toDate}";
        
        $result = Cache::remember($cacheKey, 3600, function () use ($lat, $lon, $fromDate, $toDate) {
            $appId = preg_replace('/\s+/', '', (string) env('ASTRO_APP_ID', ''));
            $appSecret = preg_replace('/\s+/', '', (string) env('ASTRO_APP_SECRET', ''));

            if (empty($appId) || empty($appSecret)) {
                return ['error' => 'Missing API credentials'];
            }

            $authString = base64_encode("{$appId}:{$appSecret}");

            try {
                $response = Http::timeout(15)
                    ->withHeaders([
                        'User-Agent' => 'php-web/1.0',
                        'Accept' => 'application/json',
                        'Authorization' => "Basic {$authString}"
                    ])
                    ->get("https://api.astronomyapi.com/api/v2/bodies/positions", [
                        'latitude' => $lat,
                        'longitude' => $lon,
                        'elevation' => 0,
                        'from_date' => $fromDate,
                        'to_date' => $toDate,
                        'time' => now('UTC')->format('H:i:s')
                    ]);

                if ($response->successful()) {
                    return $response->json();
                }

                return ['error' => 'API error: ' . $response->status()];
            } catch (\Exception $e) {
                return ['error' => $e->getMessage()];
            }
        });

        return response()->json($result);
    }
}
