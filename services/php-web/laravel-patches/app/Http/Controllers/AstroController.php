<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AstroController extends Controller
{
    public function events(Request $r)
    {
        $lat  = (float) $r->query('lat', 55.7558);
        $lon  = (float) $r->query('lon', 37.6176);
        $days = max(1, min(30, (int) $r->query('days', 7)));

        $from = now('UTC')->toDateString();
        $to   = now('UTC')->addDays($days)->toDateString();

        $mockEvents = [
            'data' => [
                'events' => [
                    [
                        'name' => 'Полнолуние',
                        'body' => 'Луна',
                        'type' => 'Фаза Луны',
                        'date' => now()->addDays(2)->format('Y-m-d H:i'),
                        'magnitude' => '-12.7'
                    ],
                    [
                        'name' => 'Венера в максимальной яркости',
                        'body' => 'Венера',
                        'type' => 'Планетарное событие',
                        'date' => now()->addDays(5)->format('Y-m-d H:i'),
                        'magnitude' => '-4.6'
                    ],
                    [
                        'name' => 'Юпитер в оппозиции',
                        'body' => 'Юпитер',
                        'type' => 'Планетарное событие',
                        'date' => now()->addDays(7)->format('Y-m-d H:i'),
                        'magnitude' => '-2.8'
                    ],
                    [
                        'name' => 'Марс достигает апогея',
                        'body' => 'Марс',
                        'type' => 'Орбитальное событие',
                        'time' => now()->addDays(10)->format('Y-m-d H:i'),
                        'altitude' => '45°'
                    ],
                    [
                        'name' => 'Меркурий в восточной элонгации',
                        'body' => 'Меркурий',
                        'type' => 'Видимость планеты',
                        'date' => now()->addDays(12)->format('Y-m-d H:i'),
                        'note' => 'Лучшее время для наблюдения'
                    ]
                ]
            ]
        ];

        return response()->json($mockEvents);
    }
}
