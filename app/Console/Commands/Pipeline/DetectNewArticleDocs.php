<?php

declare(strict_types=1);

namespace App\Console\Commands\Pipeline;

use App\Services\Pipeline\Content\ArticleImporter;
use App\Services\Pipeline\Google\ArticlesFolderLocator;
use App\Services\Pipeline\Google\GoogleDriveService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Stage 5 auto-ingest. Polls Marketing Automation ▸ Articles for .docx
 * files and native Google Docs, imports each into InsightArticle as a
 * draft. Content-hash cached in source_docx_hash so unchanged files are
 * skipped.
 *
 * Runs daily at 06:45 (before the video-detect at 07:30 so any new
 * articles are draft-ready when marketing next opens the CMS).
 */
class DetectNewArticleDocs extends Command
{
    private const NATIVE_GOOGLE_DOC_MIME = 'application/vnd.google-apps.document';

    private const DOCX_MIME = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

    protected $signature = 'pipeline:detect-new-article-docs
                            {--dry-run : List what would be imported without touching state}';

    protected $description = 'Poll Marketing Automation ▸ Articles for new .docx / Google Docs and import as drafts';

    public function handle(
        GoogleDriveService $drive,
        ArticlesFolderLocator $locator,
        ArticleImporter $importer,
    ): int {
        if (! config('pipeline.enabled')) {
            $this->warn('Pipeline disabled (PIPELINE_ENABLED=false). Skipping.');

            return self::SUCCESS;
        }

        $dry = (bool) $this->option('dry-run');

        try {
            $folderId = $locator->resolve();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $docxFiles = $drive->listFiles($folderId, self::DOCX_MIME);
        $googleDocs = $drive->listFiles($folderId, self::NATIVE_GOOGLE_DOC_MIME);
        $files = array_merge($docxFiles, $googleDocs);

        if ($files === []) {
            $this->info('No article docs in Marketing Automation ▸ Articles.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Found %d file(s).', count($files)));

        $counts = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'failed' => 0];

        foreach ($files as $file) {
            $label = $file['name'].' ('.($file['mimeType'] === self::NATIVE_GOOGLE_DOC_MIME ? 'Google Doc' : '.docx').')';

            if ($dry) {
                $this->line("  · dry — {$label}");

                continue;
            }

            try {
                $result = $importer->import($file);
                $counts[$result['action']]++;
                $verb = match ($result['action']) {
                    'created' => '<fg=green>new</>',
                    'updated' => '<fg=cyan>updated</>',
                    'unchanged' => '<fg=gray>unchanged</>',
                };
                $this->line("  · {$verb} — {$label} → insight #{$result['article']->id}");
            } catch (Throwable $e) {
                $counts['failed']++;
                $this->error("  · failed — {$label}: {$e->getMessage()}");
                Log::channel('pipeline')->error('ArticleImporter failed.', [
                    'file' => $file['name'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->newLine();
        $this->info(sprintf(
            'Done. Created %d · Updated %d · Unchanged %d · Failed %d.',
            $counts['created'],
            $counts['updated'],
            $counts['unchanged'],
            $counts['failed'],
        ));

        return $counts['failed'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
