<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

        // Получить список доступных CSV файлов
        $csvFiles = $this->getAvailableCsvFiles($search);

        return view('osdr', [
            'items' => $items,
            'total' => $total,
            'src' => $base . '/api/osdr?' . $params,
            'limit' => $limit,
            'offset' => $offset,
            'search' => $search,
            'csvFiles' => $csvFiles,
        ]);
    }

    private function getAvailableCsvFiles(string $search = ''): array
    {
        try {
            $base = $this->base();
            $params = http_build_query(array_filter([
                'limit' => 50,
                'search' => $search ?: null,
            ]));
            
            $url = $base . '/api/osdr?' . $params;
            $json = @file_get_contents($url);
            
            if (!$json) {
                return [];
            }
            
            $data = json_decode($json, true);
            $items = $data['data']['items'] ?? $data['items'] ?? [];
            
            return array_map(function ($item) {
                return [
                    'id' => $item['id'] ?? 0,
                    'dataset_id' => $item['dataset_id'] ?? '',
                    'title' => $item['title'] ?? 'Без названия',
                    'organism' => $item['organism'] ?? null,
                    'study_type' => $item['study_type'] ?? null,
                    'export_time' => $item['updated_at'] ?? $item['inserted_at'] ?? null,
                ];
            }, $items);
        } catch (\Exception $e) {
            return [];
        }
    }

    public function downloadCsv(Request $request)
    {
        $filename = $request->query('file');
        
        if ($filename) {
            return $this->downloadCsvByFilename($filename);
        }

        return $this->generateFreshCsv();
    }

    private function downloadCsvByFilename(string $filename): StreamedResponse
    {
        $safeFilename = basename($filename);
        
        $records = DB::table('osdr_exports')
            ->where('source_file', $safeFilename)
            ->orderBy('row_number')
            ->get();

        if ($records->isEmpty()) {
            abort(404, 'CSV файл не найден');
        }

        $headers = [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $safeFilename . '"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ];

        return new StreamedResponse(function () use ($records) {
            $handle = fopen('php://output', 'w');
            
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($handle, [
                'export_timestamp', 'updated_at',
                'is_public', 'has_samples', 'has_assays',
                'row_number', 'sample_count', 'assay_count',
                'dataset_id', 'title', 'organism', 'study_type', 'status',
                'raw_json'
            ]);
            
            foreach ($records as $row) {
                fputcsv($handle, [
                    $row->export_timestamp,
                    $row->updated_at ?? '',
                    $row->is_public ? 'ИСТИНА' : 'ЛОЖЬ',
                    $row->has_samples ? 'ИСТИНА' : 'ЛОЖЬ',
                    $row->has_assays ? 'ИСТИНА' : 'ЛОЖЬ',
                    $row->row_number,
                    $row->sample_count ?? 0,
                    $row->assay_count ?? 0,
                    $row->dataset_id ?? '',
                    $row->title ?? '',
                    $row->organism ?? '',
                    $row->study_type ?? '',
                    $row->status ?? '',
                    $row->raw_json ?? '{}'
                ]);
            }
            
            fclose($handle);
        }, 200, $headers);
    }

    private function generateFreshCsv(): StreamedResponse
    {
        $items = DB::table('osdr_items')
            ->orderByDesc('updated_at')
            ->limit(500)
            ->get();

        $filename = 'osdr_export_' . date('Ymd_His') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ];

        return new StreamedResponse(function () use ($items) {
            $handle = fopen('php://output', 'w');

            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // Заголовки
            fputcsv($handle, [
                'export_timestamp', 'updated_at',
                'is_public', 'has_data',
                'row_number',
                'dataset_id', 'title', 'organism', 'study_type', 'status',
                'raw_json'
            ]);
            
            $now = date('c');
            $rowNum = 0;
            
            foreach ($items as $row) {
                $rowNum++;
                $raw = $row->raw ?? '{}';
                $hasData = !empty($row->title) || !empty($row->organism);
                
                fputcsv($handle, [
                    $now,
                    $row->updated_at ?? $row->inserted_at ?? '',
                    'ИСТИНА',
                    $hasData ? 'ИСТИНА' : 'ЛОЖЬ',
                    $rowNum,
                    $row->dataset_id ?? '',
                    $row->title ?? '',
                    $row->organism ?? '',
                    $row->study_type ?? '',
                    $row->status ?? '',
                    $raw
                ]);
            }
            
            fclose($handle);
        }, 200, $headers);
    }
}
