<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SpaceController extends Controller
{
    private function nasaApiKey(): string
    {
        return getenv('NASA_API_KEY') ?: 'DEMO_KEY';
    }

    public function apod(Request $request)
    {
        $date = $request->query('date', now()->format('Y-m-d'));
        $count = $request->query('count', 1);
        
        $cacheKey = 'nasa_apod_' . $date . '_' . $count;
        
        $data = Cache::remember($cacheKey, 43200, function () use ($date, $count) {
            $url = 'https://api.nasa.gov/planetary/apod';
            $params = [
                'api_key' => $this->nasaApiKey(),
                'thumbs' => 'true',
            ];
            
            if ($count > 1) {
                $params['count'] = min($count, 10);
            } else {
                $params['date'] = $date;
            }
            
            try {
                $response = Http::timeout(10)->get($url, $params);
                if ($response->successful()) {
                    return $response->json();
                }
            } catch (\Exception $e) {
            }
            
            return [[
                'title' => 'Orion Nebula in Oxygen, Hydrogen, and Sulfur',
                'date' => now()->format('Y-m-d'),
                'explanation' => 'The Orion Nebula spans about 40 light years and is located about 1500 light years away in the constellation of Orion.',
                'url' => 'https://apod.nasa.gov/apod/image/2312/OrionNebula_HubbleGendler_4000.jpg',
                'media_type' => 'image',
            ]];
        });
        
        $items = is_array($data) && isset($data[0]) ? $data : [$data];
        
        return view('space.apod', [
            'items' => $items,
            'date' => $date,
        ]);
    }

    public function neo(Request $request)
    {
        $startDate = $request->query('start_date', now()->format('Y-m-d'));
        $endDate = $request->query('end_date', now()->addDays(7)->format('Y-m-d'));
        
        $cacheKey = 'nasa_neo_' . $startDate . '_' . $endDate;
        
        $data = Cache::remember($cacheKey, 7200, function () use ($startDate, $endDate) {
            $url = 'https://api.nasa.gov/neo/rest/v1/feed';
            $params = [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'api_key' => $this->nasaApiKey(),
            ];
            
            try {
                $response = Http::timeout(10)->get($url, $params);
                if ($response->successful()) {
                    return $response->json();
                }
            } catch (\Exception $e) {
            }
            
            return [
                'element_count' => 3,
                'near_earth_objects' => [
                    $startDate => [
                        [
                            'id' => '2021277',
                            'name' => '21277 (1996 TO5)',
                            'absolute_magnitude_h' => 16.73,
                            'estimated_diameter' => [
                                'kilometers' => ['estimated_diameter_min' => 1.6, 'estimated_diameter_max' => 3.6],
                            ],
                            'is_potentially_hazardous_asteroid' => true,
                            'close_approach_data' => [
                                [
                                    'close_approach_date' => $startDate,
                                    'relative_velocity' => ['kilometers_per_hour' => '54720'],
                                    'miss_distance' => ['kilometers' => '19508040'],
                                ],
                            ],
                        ],
                        [
                            'id' => '3426410',
                            'name' => '(2008 QV11)',
                            'absolute_magnitude_h' => 24.3,
                            'estimated_diameter' => [
                                'kilometers' => ['estimated_diameter_min' => 0.03, 'estimated_diameter_max' => 0.07],
                            ],
                            'is_potentially_hazardous_asteroid' => false,
                            'close_approach_data' => [
                                [
                                    'close_approach_date' => $startDate,
                                    'relative_velocity' => ['kilometers_per_hour' => '32400'],
                                    'miss_distance' => ['kilometers' => '7200000'],
                                ],
                            ],
                        ],
                    ],
                ],
            ];
        });
        
        $asteroids = [];
        if (isset($data['near_earth_objects'])) {
            foreach ($data['near_earth_objects'] as $date => $objects) {
                foreach ($objects as $obj) {
                    $asteroids[] = $obj;
                }
            }
        }
        
        return view('space.neo', [
            'asteroids' => $asteroids,
            'element_count' => $data['element_count'] ?? count($asteroids),
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
    }

    public function donki(Request $request)
    {
        $startDate = $request->query('start_date', now()->subDays(30)->format('Y-m-d'));
        $endDate = $request->query('end_date', now()->format('Y-m-d'));
        
        $cacheKey = 'nasa_donki_' . $startDate . '_' . $endDate;
        
        $data = Cache::remember($cacheKey, 3600, function () use ($startDate, $endDate) {
            $url = 'https://api.nasa.gov/DONKI/FLR';
            $params = [
                'startDate' => $startDate,
                'endDate' => $endDate,
                'api_key' => $this->nasaApiKey(),
            ];
            
            try {
                $response = Http::timeout(10)->get($url, $params);
                if ($response->successful()) {
                    return $response->json();
                }
            } catch (\Exception $e) {

            }
            
            return [
                [
                    'flrID' => '2023-12-01T12:34:00-FLR-001',
                    'beginTime' => '2023-12-01T12:34:00Z',
                    'peakTime' => '2023-12-01T12:45:00Z',
                    'endTime' => '2023-12-01T13:00:00Z',
                    'classType' => 'M5.5',
                    'sourceLocation' => 'N15E45',
                    'activeRegionNum' => 13511,
                ],
                [
                    'flrID' => '2023-11-28T08:15:00-FLR-001',
                    'beginTime' => '2023-11-28T08:15:00Z',
                    'peakTime' => '2023-11-28T08:30:00Z',
                    'endTime' => '2023-11-28T09:00:00Z',
                    'classType' => 'X1.2',
                    'sourceLocation' => 'S20W30',
                    'activeRegionNum' => 13509,
                ],
            ];
        });
        
        return view('space.donki', [
            'events' => $data ?? [],
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
    }

    public function spacex(Request $request)
    {
        $limit = $request->query('limit', 10);
        
        $cacheKey = 'spacex_launches_' . $limit;
        
        $data = Cache::remember($cacheKey, 3600, function () use ($limit) {
            $url = 'https://api.spacexdata.com/v5/launches/query';
            $body = [
                'query' => [],
                'options' => [
                    'limit' => min($limit, 50),
                    'sort' => ['date_utc' => 'desc'],
                    'select' => ['name', 'date_utc', 'success', 'details', 'links', 'rocket', 'launchpad'],
                ],
            ];
            
            try {
                $response = Http::timeout(10)->post($url, $body);
                if ($response->successful()) {
                    return $response->json();
                }
            } catch (\Exception $e) {

            }
            
            return [
                'docs' => [
                    [
                        'name' => 'Starlink Group 6-30',
                        'date_utc' => '2023-12-01T12:00:00.000Z',
                        'success' => true,
                        'details' => 'Successful deployment of 23 Starlink satellites to low Earth orbit.',
                        'links' => [
                            'patch' => ['small' => 'https://minio.nplus1.ru/app-images/437957/630234122ce05_cover_share.jpg'],
                            'webcast' => 'https://www.youtube.com/watch?v=P-xOqMpQ6iw',
                        ],
                    ],
                    [
                        'name' => 'Crew-7',
                        'date_utc' => '2023-08-26T07:27:00.000Z',
                        'success' => true,
                        'details' => 'Crew Dragon spacecraft carrying four astronauts to the ISS.',
                        'links' => [
                            'patch' => ['small' => 'https://cs13.pikabu.ru/post_img/big/2023/08/18/6/1692351780141133042.png'],
                            'webcast' => 'https://www.youtube.com/watch?v=5KeIAYTW8eQ',
                        ],
                    ],
                ],
            ];
        });
        
        return view('space.spacex', [
            'launches' => $data['docs'] ?? [],
            'limit' => $limit,
        ]);
    }
}
