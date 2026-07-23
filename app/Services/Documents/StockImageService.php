<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Models\DocumentArticle;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Searches Pexels for a relevant landscape stock photo and stores it on the
 * public disk as an article's cover image. Returns the storage-relative path,
 * or null when Pexels is not configured / finds nothing (callers then fall back
 * to the standard default cover).
 */
class StockImageService
{
    private const SEARCH_URL = 'https://api.pexels.com/v1/search';

    public function isConfigured(): bool
    {
        return (string) config('services.pexels.key') !== '';
    }

    /**
     * @return array{path:string,photographer:?string,source_url:?string}|null
     */
    public function searchAndStore(string $query, DocumentArticle $document): ?array
    {
        $key = (string) config('services.pexels.key');
        if ($key === '' || trim($query) === '') {
            return null;
        }

        try {
            $response = Http::withHeaders(['Authorization' => $key])
                ->timeout(15)
                ->get(self::SEARCH_URL, [
                    'query' => $query,
                    'per_page' => 1,
                    'orientation' => 'landscape',
                ]);

            if (! $response->ok()) {
                Log::warning('Pexels search failed.', ['status' => $response->status(), 'query' => $query]);

                return null;
            }

            $photo = $response->json('photos.0');
            $imageUrl = $photo['src']['large'] ?? $photo['src']['landscape'] ?? $photo['src']['original'] ?? null;
            if (! $imageUrl) {
                return null;
            }

            $bytes = Http::timeout(20)->get($imageUrl)->body();
            if ($bytes === '') {
                return null;
            }

            $path = "document-articles/{$document->id}/stock-".Str::random(8).'.jpg';
            Storage::disk('public')->put($path, $bytes);

            return [
                'path' => $path,
                'photographer' => $photo['photographer'] ?? null,
                'source_url' => $photo['url'] ?? null,
            ];
        } catch (\Throwable $e) {
            Log::warning('Pexels search errored.', ['error' => $e->getMessage(), 'query' => $query]);

            return null;
        }
    }
}
