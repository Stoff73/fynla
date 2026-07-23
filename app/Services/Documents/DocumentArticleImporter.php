<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Models\DocumentArticle;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class DocumentArticleImporter
{
    public function __construct(
        private readonly DocxMetadataExtractor $metadataExtractor,
        private readonly HTMLBodySanitiser $sanitiser,
        private readonly SlugGenerator $slugger,
    ) {}

    /**
     * @param  array<int, UploadedFile>  $imageBlobs  index → UploadedFile
     * @param  array{title: ?string, subtitle: ?string, description: ?string, keywords: ?string, author_name: ?string}  $clientMetadata
     */
    public function import(
        UploadedFile $docxFile,
        string $html,
        array $imageBlobs,
        array $clientMetadata,
        User $importedBy,
    ): DocumentArticle {
        $serverMeta = $this->metadataExtractor->extract($docxFile->getRealPath());
        $merged = $this->mergeMetadata($clientMetadata, $serverMeta);

        $title = $merged['title'] ?: $this->fallbackTitle($docxFile, $html);
        $sanitisedHtml = $this->sanitiser->sanitise($html);
        $this->validatePlaceholders($sanitisedHtml, $imageBlobs);

        // Summary/meta description: take the first real paragraph of the
        // article, capped at 100 words. Only when the document itself did not
        // carry an explicit description property.
        if (($merged['description'] ?? null) === null || trim((string) $merged['description']) === '') {
            $merged['description'] = $this->firstParagraphSummary($sanitisedHtml);
        }

        $hash = hash_file('sha256', $docxFile->getRealPath());

        return DB::transaction(function () use (
            $title, $merged, $sanitisedHtml, $imageBlobs, $importedBy, $docxFile, $hash
        ) {
            $article = DocumentArticle::create([
                'slug' => $this->slugger->unique(Str::slug($title)),
                'title' => $title,
                'subtitle' => $merged['subject'],
                'description' => $merged['description'],
                'keywords' => $merged['keywords'],
                'author_name' => $merged['creator'],
                'author_byline' => $merged['creator'],
                'cover_image_path' => null,
                'html_body' => $sanitisedHtml,
                'status' => 'draft',
                'imported_by' => $importedBy->id,
                'original_filename' => $docxFile->getClientOriginalName(),
                'original_doc_hash' => $hash,
            ]);

            $writtenPaths = [];
            try {
                foreach ($imageBlobs as $index => $blob) {
                    $ext = strtolower($blob->getClientOriginalExtension() ?: 'png');
                    $path = "document-articles/{$article->id}/img-{$index}.{$ext}";
                    $stored = Storage::disk('public')->putFileAs(
                        "document-articles/{$article->id}",
                        $blob,
                        "img-{$index}.{$ext}"
                    );
                    if ($stored === false) {
                        throw new RuntimeException("Failed to write image index {$index} for article {$article->id}.");
                    }
                    $writtenPaths[$index] = $path;
                }
            } catch (\Throwable $e) {
                foreach ($writtenPaths as $p) {
                    Storage::disk('public')->delete($p);
                }
                throw $e;
            }

            $finalHtml = $this->rewritePlaceholders($sanitisedHtml, $writtenPaths);

            $coverPath = $writtenPaths[0] ?? null;
            $article->update([
                'html_body' => $finalHtml,
                'cover_image_path' => $coverPath,
            ]);

            // No image embedded in the document → try a relevant stock photo
            // (Pexels) by the article title. Non-fatal: if it's unconfigured or
            // finds nothing, the article falls back to the default cover.
            if ($coverPath === null) {
                $stock = app(StockImageService::class)->searchAndStore((string) $article->title, $article);
                if ($stock !== null) {
                    $article->update(['cover_image_path' => $stock['path']]);
                }
            }

            return $article->fresh();
        });
    }

    /**
     * @return array{title: ?string, subject: ?string, description: ?string, keywords: ?string, creator: ?string}
     */
    private function mergeMetadata(array $client, array $server): array
    {
        return [
            'title' => $server['title'] ?? $client['title'] ?? null,
            'subject' => $server['subject'] ?? $client['subtitle'] ?? null,
            'description' => $server['description'] ?? $client['description'] ?? null,
            'keywords' => $server['keywords'] ?? $client['keywords'] ?? null,
            'creator' => $server['creator'] ?? $client['author_name'] ?? null,
        ];
    }

    private function fallbackTitle(UploadedFile $docx, string $html): string
    {
        if (preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $html, $m)) {
            $candidate = trim(strip_tags($m[1]));
            if ($candidate !== '') {
                return $candidate;
            }
        }
        $name = pathinfo($docx->getClientOriginalName(), PATHINFO_FILENAME);

        return $name !== '' ? $name : 'Untitled document';
    }

    /**
     * The article summary: the first non-empty paragraph, stripped of markup
     * and capped at 100 words. Returns null when the body has no paragraph
     * text to summarise.
     */
    private function firstParagraphSummary(string $html): ?string
    {
        if (preg_match_all('/<p\b[^>]*>(.*?)<\/p>/is', $html, $matches)) {
            foreach ($matches[1] as $paragraph) {
                $text = trim(html_entity_decode(strip_tags($paragraph), ENT_QUOTES | ENT_HTML5));
                if ($text === '') {
                    continue;
                }
                $words = preg_split('/\s+/', $text) ?: [];
                if (count($words) > 100) {
                    return implode(' ', array_slice($words, 0, 100)).'…';
                }

                return $text;
            }
        }

        return null;
    }

    /**
     * @param  array<int, UploadedFile>  $imageBlobs
     */
    private function validatePlaceholders(string $html, array $imageBlobs): void
    {
        preg_match_all('/data-pending-image="(\d+)"/', $html, $matches);
        $referenced = array_unique(array_map('intval', $matches[1] ?? []));
        foreach ($referenced as $index) {
            if (! array_key_exists($index, $imageBlobs)) {
                throw new RuntimeException("HTML references image index {$index} but no blob was supplied.");
            }
        }
    }

    /**
     * @param  array<int, string>  $writtenPaths  index → storage path
     */
    private function rewritePlaceholders(string $html, array $writtenPaths): string
    {
        return preg_replace_callback(
            '/<img\b([^>]*)\bdata-pending-image="(\d+)"([^>]*)>/i',
            function (array $m) use ($writtenPaths): string {
                $idx = (int) $m[2];
                if (! isset($writtenPaths[$idx])) {
                    return '';
                }
                $url = '/storage/'.$writtenPaths[$idx];
                $other = $m[1].$m[3];
                $other = preg_replace('/\s*data-pending-image="\d+"/', '', $other);

                return '<img'.$other.' src="'.$url.'">';
            },
            $html
        );
    }
}
