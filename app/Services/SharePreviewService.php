<?php

namespace App\Services;

use Illuminate\Support\Str;

class SharePreviewService
{
    public function forPage(array $page): array
    {
        $title = 'Tarombo Batak';
        $description = 'Jelajahi silsilah, cerita, berita, dan kegiatan keluarga Batak di Tarombo Batak.';
        $image = asset('Brand.png');
        $props = $page['props'] ?? [];
        $content = match ($page['component'] ?? '') {
            'kegiatan/show' => $props['event'] ?? [],
            'cerita/show' => $props['story'] ?? [],
            'marga-news/show' => $props['news'] ?? [],
            'news-feed/status' => ($props['item']['audience'] ?? '') === 'public' ? ($props['item'] ?? []) : [],
            default => [],
        };

        if ($content !== []) {
            $description = $content['description'] ?? $content['excerpt'] ?? $content['summary'] ?? $content['body'] ?? $description;
            $title = $content['title'] ?? 'Status '.$content['author'];
            $image = $content['image_url'] ?? $content['image'] ?? ($content['images'][0] ?? $image);
        } elseif (($page['component'] ?? '') === 'news-feed/index') {
            $title = 'News Feed · Tarombo Batak';
        }

        $description = Str::limit(Str::squish(html_entity_decode(strip_tags($description), ENT_QUOTES, 'UTF-8')), 180);
        $image = preg_match('/^https?:\/\//i', $image) ? $image : url($image);

        return ['title' => $title, 'description' => $description, 'image' => $image, 'url' => request()->url()];
    }
}
