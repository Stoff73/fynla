<?php

declare(strict_types=1);

namespace App\Services\AI\Memory;

use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

/**
 * Runtime per-user semantic facts (CoALA Phase 6). SEPARATE from the committed,
 * CSJ-authored global corpus (SemanticCorpusLoader) — the learning path never
 * writes that corpus. Facts here are reified ONLY from an approved
 * ProposedSemanticFact. Lives under storage/app (gitignored, per-user, like
 * episodes). GDPR erasure deletes the user's directory.
 */
final class UserSemanticStore
{
    public function put(int $userId, string $factId, string $title, string $body): string
    {
        $dir = $this->userDir($userId);
        File::ensureDirectoryExists($dir);

        $filename = preg_replace('/[^a-z0-9\-]/', '', mb_strtolower($factId));
        if ($filename === '') {
            throw new \InvalidArgumentException("factId '{$factId}' produces an empty filename after sanitisation.");
        }
        $path = $dir.'/'.$filename.'.md';

        $meta = ['fact_id' => $factId, 'category' => 'user_profile', 'title' => $title, 'version' => 1];
        File::put($path, "---\n".Yaml::dump($meta, 4, 2)."---\n\n".trim($body)."\n");

        return $path;
    }

    /** @return list<array{fact_id: string, title: string, body: string}> */
    public function forUser(int $userId): array
    {
        $dir = $this->userDir($userId);
        if (! File::isDirectory($dir)) {
            return [];
        }

        $facts = [];
        foreach (File::files($dir) as $file) {
            if ($file->getExtension() !== 'md') {
                continue;
            }
            [$meta, $body] = $this->parse(File::get($file->getPathname()));
            $facts[] = [
                'fact_id' => (string) ($meta['fact_id'] ?? $file->getFilenameWithoutExtension()),
                'title' => (string) ($meta['title'] ?? ''),
                'body' => trim($body),
            ];
        }

        return $facts;
    }

    public function forget(int $userId): void
    {
        $dir = $this->userDir($userId);
        if (File::isDirectory($dir)) {
            File::deleteDirectory($dir);
        }
    }

    private function userDir(int $userId): string
    {
        return rtrim((string) config('fyn.memory.user_semantic_path'), '/').'/'.$userId;
    }

    /** @return array{0: array<string, mixed>, 1: string} */
    private function parse(string $contents): array
    {
        if (preg_match('/^---\s*\n(.*?)\n---\s*\n?(.*)$/s', $contents, $m) === 1) {
            $meta = Yaml::parse($m[1]);

            return [is_array($meta) ? $meta : [], $m[2]];
        }

        return [[], $contents];
    }
}
