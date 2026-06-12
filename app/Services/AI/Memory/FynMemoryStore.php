<?php

declare(strict_types=1);

namespace App\Services\AI\Memory;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;

/**
 * Read/write adapter for Fyn's markdown memory stores (CoALA Phase 5 — the store
 * half of FR-M2). Backs the loop's `retrieve` (procedural corpus + episodic
 * recall) and `learn` (write an episode) actions.
 *
 * Files and layout: see fyn-memory/README.md. Paths from config('fyn.memory').
 * This is pure file I/O over markdown-with-YAML-frontmatter — the salience
 * decision (apply the rubric) is the planner's job; the planner emits a `learn`
 * action whose payload this writes verbatim.
 */
final class FynMemoryStore
{
    /** Filenames in procedural/ that are scaffolding, not procedures. */
    private const PROCEDURAL_SKIP = ['_TEMPLATE.md', 'README.md'];

    /**
     * Load every authored procedure (parsed frontmatter + body).
     *
     * @return list<array{id: string, title: string, applies_when: string, version: mixed, body: string}>
     */
    public function procedures(): array
    {
        $dir = (string) config('fyn.memory.procedural_path');
        if (! File::isDirectory($dir)) {
            return [];
        }

        $procedures = [];
        foreach (File::files($dir) as $file) {
            if ($file->getExtension() !== 'md' || in_array($file->getFilename(), self::PROCEDURAL_SKIP, true)) {
                continue;
            }

            [$meta, $body] = $this->parse(File::get($file->getPathname()));

            $procedures[] = [
                'id' => (string) ($meta['id'] ?? $file->getFilenameWithoutExtension()),
                'title' => (string) ($meta['title'] ?? ''),
                'applies_when' => trim((string) ($meta['applies_when'] ?? '')),
                'version' => $meta['version'] ?? null,
                'body' => trim($body),
            ];
        }

        return $procedures;
    }

    /**
     * The procedures relevant to one turn. Null query = the FULL corpus —
     * the planner's path (FynLoop::plannerSystemPrompt), because matching
     * applies_when to intent IS the planner's job. With a query, a procedure
     * is admitted under the same relevance contract as SemanticRetriever
     * (§8.2 lean-prompt law): stopword-dropped query tokens, word-boundary
     * token counting over title + applies_when + body with simple ±"s"
     * plural variants, admitted at >= min(2, distinct content tokens)
     * distinct matches.
     *
     * @return list<array{id: string, title: string, applies_when: string, version: mixed, body: string}>
     */
    public function matchingProcedures(?string $query = null): array
    {
        $procedures = $this->procedures();
        if ($query === null || $procedures === []) {
            return $procedures;
        }

        $terms = array_values(array_diff($this->tokenise($query), SemanticRetriever::STOPWORDS));
        if ($terms === []) {
            return [];
        }

        $required = min(2, count($terms));

        return array_values(array_filter($procedures, function (array $p) use ($terms, $required): bool {
            $counts = $this->termCounts("{$p['title']} {$p['applies_when']} {$p['body']}");
            $distinct = 0;
            foreach ($terms as $term) {
                if ($this->termHits($term, $counts) > 0) {
                    $distinct++;
                }
            }

            return $distinct >= $required;
        }));
    }

    /**
     * The procedural corpus as a context block for the planner/reasoner. Empty
     * string when no procedures are authored yet (or none match the query).
     */
    public function proceduralContext(?string $query = null): string
    {
        $procedures = $this->matchingProcedures($query);
        if ($procedures === []) {
            return '';
        }

        $blocks = array_map(
            static fn (array $p): string => "### {$p['title']} (applies when: {$p['applies_when']})\n{$p['body']}",
            $procedures,
        );

        return "## Procedures\n\n".implode("\n\n", $blocks);
    }

    /**
     * The episodic capture rubric the planner applies to decide whether to
     * `learn`. Returns '' while the rubric is still a draft (status: draft or
     * version 0) so the unfinished scaffold is never injected into the planner —
     * CSJ bumps the version / clears the draft status once it's authored.
     */
    public function rubric(): string
    {
        $path = (string) config('fyn.memory.episodic_rubric');
        if (! File::exists($path)) {
            return '';
        }

        [$meta, $body] = $this->parse(File::get($path));

        $isDraft = ($meta['status'] ?? null) === 'draft' || (int) ($meta['version'] ?? 0) < 1;

        return $isDraft ? '' : trim($body);
    }

    /**
     * Recall a user's most recent episodes (newest first). Episode filenames are
     * date-prefixed, so a reverse name sort is recency order without parsing.
     *
     * @return list<array{meta: array<string, mixed>, body: string, path: string}>
     */
    public function recall(int $userId, int $limit = 5): array
    {
        $userDir = $this->userEpisodeDir($userId);
        if (! File::isDirectory($userDir)) {
            return [];
        }

        // episodes live at {userDir}/{year}/{file}.md
        $files = File::glob($userDir.'/*/*.md') ?: [];
        rsort($files);
        $files = array_slice($files, 0, max(0, $limit));

        return array_map(function (string $path): array {
            [$meta, $body] = $this->parse(File::get($path));

            return ['meta' => $meta, 'body' => trim($body), 'path' => $path];
        }, $files);
    }

    /**
     * Recalled episodes as a context block. Empty string when the user has none.
     */
    public function recallContext(int $userId, int $limit = 5): string
    {
        $episodes = $this->recall($userId, $limit);
        if ($episodes === []) {
            return '';
        }

        $blocks = array_map(
            static fn (array $e): string => '- '.trim((string) Str::before($e['body'], "\n## Detail")),
            $episodes,
        );

        return "## What I remember about you\n\n".implode("\n", $blocks);
    }

    /**
     * Write one episode (the planner already decided it is salient). Returns the
     * written path. The store stamps user_id / conversation_id / recorded_at;
     * the rest of $episode (summary, detail, salience, signals, references,
     * procedural_version) is the planner's payload.
     *
     * @param  array<string, mixed>  $episode
     */
    public function writeEpisode(int $userId, int $conversationId, array $episode): string
    {
        $now = Carbon::now();
        $year = $now->format('Y');
        $dir = $this->userEpisodeDir($userId).'/'.$year;
        File::ensureDirectoryExists($dir);

        $slug = Str::slug(Str::limit((string) ($episode['summary'] ?? 'episode'), 40, ''));
        $slug = $slug !== '' ? $slug : 'episode';
        $filename = $now->format('Y-m-d-His').'-'.$slug.'-'.Str::lower(Str::random(4)).'.md';
        $path = $dir.'/'.$filename;

        $meta = [
            'user_id' => $userId,
            'conversation_id' => $conversationId,
            'recorded_at' => $now->toIso8601String(),
            'salience' => (int) ($episode['salience'] ?? 0),
            'signals' => array_values((array) ($episode['signals'] ?? [])),
            'references' => array_values((array) ($episode['references'] ?? [])),
            'procedural_version' => (string) ($episode['procedural_version'] ?? ''),
        ];

        $body = "## Summary\n\n".trim((string) ($episode['summary'] ?? ''));
        if (! empty($episode['detail'])) {
            $body .= "\n\n## Detail\n\n".trim((string) $episode['detail']);
        }

        File::put($path, "---\n".Yaml::dump($meta, 4, 2)."---\n\n".$body."\n");

        return $path;
    }

    /**
     * GDPR — delete a user's entire episode tree (right to erasure).
     */
    public function forget(int $userId): void
    {
        $userDir = $this->userEpisodeDir($userId);
        if (File::isDirectory($userDir)) {
            File::deleteDirectory($userDir);
        }
    }

    private function userEpisodeDir(int $userId): string
    {
        return rtrim((string) config('fyn.memory.episodic_path'), '/').'/'.$userId;
    }

    /**
     * Same token grammar as SemanticRetriever::tokenise (the §8.2 contract).
     *
     * @return list<string>
     */
    private function tokenise(string $text): array
    {
        preg_match_all('/[a-z0-9]{3,}/', mb_strtolower($text), $m);

        return array_values(array_unique($m[0]));
    }

    /**
     * Word-boundary token frequency map of the haystack — mirror of
     * SemanticRetriever::termCounts, so "the" never matches inside "them".
     *
     * @return array<string, int>
     */
    private function termCounts(string $haystack): array
    {
        preg_match_all('/[a-z0-9]{3,}/', mb_strtolower($haystack), $m);

        return array_count_values($m[0]);
    }

    /**
     * Exact-token hits plus simple ±"s" plural variants — mirror of
     * SemanticRetriever::termHits. No deeper stemming.
     *
     * @param  array<string, int>  $counts
     */
    private function termHits(string $term, array $counts): int
    {
        $hits = ($counts[$term] ?? 0) + ($counts[$term.'s'] ?? 0);
        if (str_ends_with($term, 's')) {
            $hits += $counts[substr($term, 0, -1)] ?? 0;
        }

        return $hits;
    }

    /**
     * Split a markdown-with-frontmatter document into [meta, body].
     *
     * @return array{0: array<string, mixed>, 1: string}
     */
    private function parse(string $contents): array
    {
        if (preg_match('/^---\s*\n(.*?)\n---\s*\n?(.*)$/s', $contents, $m) === 1) {
            $meta = Yaml::parse($m[1]);

            return [is_array($meta) ? $meta : [], $m[2]];
        }

        return [[], $contents];
    }
}
