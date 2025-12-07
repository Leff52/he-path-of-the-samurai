<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ProxyController extends Controller
{
    private function base(): string {
        return getenv('RUST_BASE') ?: 'http://rust_iss:3000';
    }

    // ISS endpoints
    public function last()  { return $this->pipe('/api/iss/latest'); }
    public function latest()  { return $this->pipe('/api/iss/latest'); }

    public function trend(Request $request) {
        $hours = $request->get('hours', 24);
        return $this->pipe('/api/iss/trend?hours=' . intval($hours));
    }

    // OSDR endpoints
    public function osdrList(Request $request) {
        $params = http_build_query([
            'limit' => $request->get('limit', 20),
            'offset' => $request->get('offset', 0),
            'search' => $request->get('search', ''),
        ]);
        return $this->pipe('/api/osdr?' . $params);
    }

    // Space cache endpoints
    public function spaceCache(string $source) {
        $allowedSources = ['apod', 'neo', 'flr', 'cme', 'spacex'];
        if (!in_array($source, $allowedSources)) {
            return response()->json([
                'ok' => false,
                'error' => ['code' => 'INVALID_SOURCE', 'message' => 'Unknown source']
            ]);
        }
        return $this->pipe('/api/space/cache/' . $source);
    }

    private function pipe(string $path): Response
    {
        $url = $this->base() . $path;
        try {
            $ctx = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'ignore_errors' => true,
                    'header' => 'Accept: application/json',
                ],
            ]);
            $body = @file_get_contents($url, false, $ctx);
            
            if ($body === false || trim($body) === '') {
                return new Response(
                    json_encode(['ok' => false, 'error' => ['code' => 'UPSTREAM_ERROR', 'message' => 'No response']]),
                    200,
                    ['Content-Type' => 'application/json']
                );
            }
            
            // Validate JSON
            json_decode($body);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return new Response(
                    json_encode(['ok' => false, 'error' => ['code' => 'INVALID_JSON', 'message' => 'Invalid JSON response']]),
                    200,
                    ['Content-Type' => 'application/json']
                );
            }
            
            return new Response($body, 200, ['Content-Type' => 'application/json']);
        } catch (\Throwable $e) {
            return new Response(
                json_encode(['ok' => false, 'error' => ['code' => 'UPSTREAM_ERROR', 'message' => $e->getMessage()]]),
                200,
                ['Content-Type' => 'application/json']
            );
        }
    }
}
