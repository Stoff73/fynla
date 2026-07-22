<?php

declare(strict_types=1);

namespace App\Services\Pipeline\Google;

/**
 * Given a batch of Drive change records, decides which pipeline detectors to
 * run: a new/updated .docx (or Google Doc) in the Articles folder → the article
 * detectors; a new .mp4 in the Videos folder → the video detector. Everything
 * else in the drive is ignored.
 */
class DriveChangeRouter
{
    public function __construct(
        private readonly ArticlesFolderLocator $articles,
        private readonly VideosFolderLocator $videos,
    ) {}

    /**
     * @param  list<array<string,mixed>>  $changes  from GoogleDriveService::listChanges()
     * @return array{articles:bool,videos:bool}
     */
    public function classify(array $changes): array
    {
        $articlesId = $this->safeResolve($this->articles);
        $videosId = $this->safeResolve($this->videos);

        $hitArticles = false;
        $hitVideos = false;

        foreach ($changes as $change) {
            $file = $change['file'] ?? null;
            if (! is_array($file) || ($file['trashed'] ?? false) === true) {
                continue;
            }

            $parents = is_array($file['parents'] ?? null) ? $file['parents'] : [];
            $name = strtolower((string) ($file['name'] ?? ''));
            $mime = (string) ($file['mimeType'] ?? '');

            if ($articlesId !== null && in_array($articlesId, $parents, true)
                && (str_ends_with($name, '.docx') || $mime === 'application/vnd.google-apps.document')) {
                $hitArticles = true;
            }

            if ($videosId !== null && in_array($videosId, $parents, true) && str_ends_with($name, '.mp4')) {
                $hitVideos = true;
            }
        }

        return ['articles' => $hitArticles, 'videos' => $hitVideos];
    }

    private function safeResolve(ArticlesFolderLocator|VideosFolderLocator $locator): ?string
    {
        try {
            return $locator->resolve();
        } catch (\Throwable) {
            return null;
        }
    }
}
