<?php

namespace App\Http\Controllers;

use App\Repositories\CmsRepository;
use Illuminate\Support\Facades\Log;

class CmsController extends Controller
{
    public function __construct(
        private CmsRepository $cmsRepository
    ) {}
    
    public function page(string $slug)
    {
        $page = $this->cmsRepository->findBySlug($slug);
        
        if (!$page) {
            Log::warning('CMS page not found', ['slug' => $slug]);
            abort(404);
        }
        
        return view('cms.page', [
            'title' => $page->title,
            // Используем экранированный вывод {{ }} вместо {!! !!}
            'body' => $page->body,
        ]);
    }
}

