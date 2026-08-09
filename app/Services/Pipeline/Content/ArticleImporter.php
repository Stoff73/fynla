<?php

declare(strict_types=1);

namespace App\Services\Pipeline\Content;

use App\Models\Insights\InsightArticle;
use App\Models\Pipeline\ArticleSyncLog;
use App\Services\Pipeline\Google\GoogleDriveService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Orchestrates one Word-doc → InsightArticle import.
 *
 * Steps:
 *   1. Download the file from Drive (native Google Doc → export to .docx first).
 *   2. Parse via WordDocxIngestor.
 *   3. Skip if content hash matches the existing article's source_docx_hash.
 *   4. Create a new InsightArticle in `draft` status, or update the existing one.
 *   5. Write an article_sync_log row for the local ingest.
 *
 * Slug derivation: filename minus extension, slugified. If a different
 * article already owns that slug, appends a random suffix (avoids
 * collision with human-authored articles).
 */
class ArticleImporter
{
    private const NATIVE_GOOGLE_DOC_MIME = 'application/vnd.google-apps.document';

    private const DOCX_MIME = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

    public function __construct(
        private readonly GoogleDriveService $drive,
        private readonly GoogleDocExporter $exporter,
        private readonly WordDocxIngestor $ingestor,
    ) {}

    /**
     * @param  array{id:string,name:string,mimeType:string,webViewLink?:string}  $file
     * @return array{article: InsightArticle, action: 'created'|'updated'|'unchanged'}
     */
    public function import(array $file): array
    {
        $existing = InsightArticle::where('source_docx_drive_file_id', $file['id'])->first();

        $localPath = $this->downloadToTemp($file);

        try {
            $parsed = $this->ingestor->ingest($localPath);
        } finally {
            @unlink($localPath);
        }

        if ($existing !== null && $existing->source_docx_hash === $parsed['hash']) {
            return ['article' => $existing, 'action' => 'unchanged'];
        }

        $result = DB::transaction(function () use ($existing, $file, $parsed) {
            $title = $this->titleFromParse($parsed['blocks']) ?? $this->titleFromFilename($file['name']);
            $slug = $existing?->slug ?? $this->uniqueSlug($file['name']);
            $summary = $this->summaryFromParse($parsed['blocks']);
            $now = now();

            $payload = [
                'slug' => $slug,
                'title' => $title,
                'summary' => $summary ?? substr(strip_tags($title), 0, 300),
                'body_blocks' => $parsed['blocks'],
                'source_docx_drive_file_id' => $file['id'],
                'source_docx_drive_url' => $file['webViewLink'] ?? ('https://drive.google.com/file/d/'.$file['id'].'/view'),
                'source_docx_hash' => $parsed['hash'],
                'source_docx_imported_at' => $now,
            ];

            if ($existing === null) {
                $payload['status'] = 'draft';
                $payload['category'] = 'financial-planning';
                $article = InsightArticle::create($payload);
                $action = 'created';
            } else {
                $existing->update($payload);
                $article = $existing->fresh();
                $action = 'updated';
            }

            ArticleSyncLog::create([
                'insight_article_id' => $article->id,
                'target_env' => 'local',
                'action' => $action === 'created' ? 'sync' : 'reimport',
                'status' => 'success',
                'payload_hash' => $parsed['hash'],
                'metadata' => [
                    'drive_file_id' => $file['id'],
                    'drive_file_name' => $file['name'],
                    'block_count' => count($parsed['blocks']),
                    'image_count' => $parsed['image_count'] ?? 0,
                ],
            ]);

            return ['article' => $article, 'action' => $action];
        });

        Log::channel('pipeline')->info('ArticleImporter: '.$result['action'].' article from Word doc.', [
            'insight_article_id' => $result['article']->id,
            'drive_file_id' => $file['id'],
            'slug' => $result['article']->slug,
        ]);

        return $result;
    }

    private function downloadToTemp(array $file): string
    {
        $tmpDir = storage_path('app/pipeline/imports');
        if (! is_dir($tmpDir) && ! mkdir($tmpDir, 0755, true) && ! is_dir($tmpDir)) {
            throw new RuntimeException("Cannot create imports tmp dir: {$tmpDir}");
        }
        $localPath = $tmpDir.DIRECTORY_SEPARATOR.$file['id'].'.docx';

        if (($file['mimeType'] ?? '') === self::NATIVE_GOOGLE_DOC_MIME) {
            $this->exporter->exportToDocx($file['id'], $localPath);

            return $localPath;
        }

        if (($file['mimeType'] ?? '') !== self::DOCX_MIME) {
            throw new RuntimeException(
                'Unsupported file type: '.($file['mimeType'] ?? 'unknown')
                .' — only .docx and native Google Docs are accepted.'
            );
        }

        $this->drive->downloadFile($file['id'], $localPath);

        return $localPath;
    }

    private function titleFromParse(array $blocks): ?string
    {
        foreach ($blocks as $block) {
            if (($block['type'] ?? '') === 'heading' && ($block['level'] ?? 0) === 2) {
                return trim((string) ($block['text'] ?? ''));
            }
        }

        return null;
    }

    private function summaryFromParse(array $blocks): ?string
    {
        foreach ($blocks as $block) {
            if (($block['type'] ?? '') !== 'paragraph') {
                continue;
            }
            $text = trim(strip_tags((string) ($block['html'] ?? '')));
            if ($text !== '') {
                return mb_substr($text, 0, 300);
            }
        }

        return null;
    }

    private function titleFromFilename(string $name): string
    {
        $base = pathinfo($name, PATHINFO_FILENAME);

        return ucfirst(str_replace('-', ' ', Str::slug($base, ' ')));
    }

    private function uniqueSlug(string $filename): string
    {
        $base = Str::slug(pathinfo($filename, PATHINFO_FILENAME));
        if (! InsightArticle::where('slug', $base)->exists()) {
            return $base;
        }

        return $base.'-'.Str::lower(Str::random(6));
    }
}
