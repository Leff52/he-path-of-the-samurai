<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use App\Models\CmsPage;

class CmsRepository
{
    public function findBySlug(string $slug): ?CmsPage
    {
        // (только буквы, цифры, дефис)
        if (!preg_match('/^[a-z0-9\-]+$/', $slug)) {
            return null;
        }
        
        $row = DB::selectOne(
            "SELECT id, slug, title, body, is_active, created_at, updated_at 
             FROM cms_pages 
             WHERE slug = ? AND is_active = TRUE",
            [$slug]
        );
        
        if (!$row) {
            return null;
        }
        
        return new CmsPage(
            id: $row->id,
            slug: $row->slug,
            title: $row->title,
            body: $this->sanitizeHtml($row->body),
            isActive: $row->is_active,
            createdAt: new \DateTime($row->created_at),
            updatedAt: new \DateTime($row->updated_at),
        );
    }
    
    public function getAllActive(): array
    {
        $rows = DB::select(
            "SELECT id, slug, title, body, is_active, created_at, updated_at 
             FROM cms_pages 
             WHERE is_active = TRUE 
             ORDER BY title"
        );
        
        return array_map(function ($row) {
            return new CmsPage(
                id: $row->id,
                slug: $row->slug,
                title: $row->title,
                body: $this->sanitizeHtml($row->body),
                isActive: $row->is_active,
                createdAt: new \DateTime($row->created_at),
                updatedAt: new \DateTime($row->updated_at),
            );
        }, $rows);
    }
    
    private function sanitizeHtml(string $html): string
    {
        $allowedTags = '<p><a><b><i><strong><em><br><ul><ol><li><h1><h2><h3><h4><h5><h6><blockquote><code><pre>';
        
        // Strip tags, оставляя только разрешённые
        $clean = strip_tags($html, $allowedTags);
        
        // Дополнительная очистка через htmlspecialchars для атрибутов
        return $clean;
    }
    
    /**
     * Создание новой страницы
     */
    public function create(string $slug, string $title, string $body): ?CmsPage
    {
        if (!preg_match('/^[a-z0-9\-]+$/', $slug)) {
            throw new \InvalidArgumentException('Invalid slug format');
        }
        
        $sanitizedBody = $this->sanitizeHtml($body);
        
        $id = DB::table('cms_pages')->insertGetId([
            'slug' => $slug,
            'title' => $title,
            'body' => $sanitizedBody,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        return $this->findBySlug($slug);
    }
    
    //
    public function update(string $slug, string $title, string $body): bool
    {
        if (!preg_match('/^[a-z0-9\-]+$/', $slug)) {
            return false;
        }
        
        $sanitizedBody = $this->sanitizeHtml($body);
        
        $updated = DB::table('cms_pages')
            ->where('slug', $slug)
            ->update([
                'title' => $title,
                'body' => $sanitizedBody,
                'updated_at' => now(),
            ]);
        
        return $updated > 0;
    }
}
