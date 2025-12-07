<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class OsdrController extends Controller
{
    private function base(): string
    {
        return getenv('RUST_BASE') ?: 'http://rust_iss:3000';
    }

    public function index(Request $request)
    {
        $limit = max(1, min(100, (int) $request->query('limit', 20)));
        $offset = max(0, (int) $request->query('offset', 0));
        $search = trim((string) $request->query('search', ''));

        $base = $this->base();
        $params = http_build_query(array_filter([
            'limit' => $limit,
            'offset' => $offset,
            'search' => $search ?: null,
        ]));

        $cacheKey = 'osdr_list_' . md5($params);
        
        $data = Cache::remember($cacheKey, 60, function () use ($base, $params) {
            $url = $base . '/api/osdr?' . $params;
            $json = @file_get_contents($url);
            return $json ? json_decode($json, true) : ['ok' => false, 'data' => ['items' => [], 'total' => 0]];
        });

        $items = $data['data']['items'] ?? $data['items'] ?? [];
        $total = $data['data']['total'] ?? count($items);

        return view('osdr', [
            'items' => $items,
            'total' => $total,
            'src' => $base . '/api/osdr?' . $params,
            'limit' => $limit,
            'offset' => $offset,
            'search' => $search,
        ]);
    }
}
