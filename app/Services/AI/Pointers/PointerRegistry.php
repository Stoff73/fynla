<?php

declare(strict_types=1);

namespace App\Services\AI\Pointers;

use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

/**
 * Loads + validates the pointer corpus (fyn-memory/procedural/pointers/*.md),
 * fail-closed. Routes each pointer's `handler` to a registered FetchHandler and
 * refuses to load a pointer whose handler is not on the whitelist. Mirrors
 * SemanticCorpusLoader's parser.
 */
final class PointerRegistry
{
    private const SKIP = ['_TEMPLATE.md', 'README.md'];

    private const MODES = ['prefetch', 'tool', 'both'];

    /** @var array<string, Pointer>|null */
    private ?array $cache = null;

    public function __construct(private readonly FetchHandlerRegistry $handlers) {}

    /** @return array<string, Pointer> keyed by pointer_id */
    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $dir = (string) config('fyn.memory.pointers_path');
        $pointers = [];

        if (! File::isDirectory($dir)) {
            return $this->cache = [];
        }

        foreach (File::files($dir) as $file) {
            if ($file->getExtension() !== 'md' || in_array($file->getFilename(), self::SKIP, true)) {
                continue;
            }

            $p = $this->parse($file->getPathname(), File::get($file->getPathname()));

            if (isset($pointers[$p->pointerId])) {
                throw new RuntimeException("Pointer registry: duplicate pointer_id '{$p->pointerId}' ({$file->getPathname()}).");
            }

            $pointers[$p->pointerId] = $p;
        }

        return $this->cache = $pointers;
    }

    /** Pre-fetch pointers whose triggers appear in the query (sparse). @return list<Pointer> */
    public function matchPrefetch(string $query): array
    {
        $q = mb_strtolower($query);
        $matched = [];

        foreach ($this->all() as $p) {
            if (! $p->isPrefetch()) {
                continue;
            }
            foreach ($p->triggers as $t) {
                if ($t !== '' && str_contains($q, mb_strtolower((string) $t))) {
                    $matched[] = $p;
                    break;
                }
            }
        }

        return $matched;
    }

    /** Pointers exposed as LLM tools. @return list<Pointer> */
    public function toolPointers(): array
    {
        return array_values(array_filter($this->all(), static fn (Pointer $p): bool => $p->isTool()));
    }

    public function get(string $pointerId): ?Pointer
    {
        return $this->all()[$pointerId] ?? null;
    }

    private function parse(string $path, string $contents): Pointer
    {
        if (preg_match('/^---\s*\n(.*?)\n---\s*\n?(.*)$/s', $contents, $m) !== 1) {
            throw new RuntimeException("Pointer registry: missing frontmatter ({$path}).");
        }

        $meta = Yaml::parse($m[1]);
        if (! is_array($meta)) {
            throw new RuntimeException("Pointer registry: frontmatter must be a YAML mapping ({$path}).");
        }

        $require = function (string $k) use ($meta, $path): mixed {
            if (! array_key_exists($k, $meta) || $meta[$k] === null || $meta[$k] === '') {
                throw new RuntimeException("Pointer registry: missing '{$k}' ({$path}).");
            }

            return $meta[$k];
        };

        $mode = (string) $require('mode');
        if (! in_array($mode, self::MODES, true)) {
            throw new RuntimeException("Pointer registry: unknown mode '{$mode}' ({$path}).");
        }

        $triggers = array_values(array_map('strval', (array) ($meta['triggers'] ?? [])));
        if (($mode === 'prefetch' || $mode === 'both') && $triggers === []) {
            throw new RuntimeException("Pointer registry: prefetch/both pointer needs triggers ({$path}).");
        }

        $handler = (string) $require('handler');
        if (! $this->handlers->has($handler)) {
            throw new RuntimeException("Pointer registry: pointer references unregistered handler '{$handler}' ({$path}).");
        }

        return new Pointer(
            pointerId: (string) $require('pointer_id'),
            topic: (string) $require('topic'),
            triggers: $triggers,
            mode: $mode,
            handler: $handler,
            sourceLabel: (string) $require('source_label'),
            version: (int) $require('version'),
            body: trim($m[2]),
        );
    }
}
