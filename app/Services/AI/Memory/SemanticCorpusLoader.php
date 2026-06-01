<?php

declare(strict_types=1);

namespace App\Services\AI\Memory;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

/**
 * Loads + validates the semantic-memory corpus (fyn-memory/semantic/{category}/*.md).
 * Fail-closed: a malformed corpus throws rather than silently serving a partial
 * knowledge base to a compliance-facing assistant. Mirrors FynMemoryStore::parse.
 */
final class SemanticCorpusLoader
{
    private const SKIP = ['_TEMPLATE.md', 'README.md'];

    private const CATEGORIES = ['fca', 'product', 'house_view', 'tax', 'allowance'];

    /** source mandatory for these; valid_from mandatory for those. */
    private const SOURCE_REQUIRED = ['fca', 'product'];

    private const VALID_FROM_REQUIRED = ['fca', 'tax', 'allowance'];

    /** @var array<string, SemanticFact>|null */
    private ?array $cache = null;

    /** @return array<string, SemanticFact> keyed by fact_id */
    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $root = (string) config('fyn.memory.semantic_path');
        $facts = [];

        if (! File::isDirectory($root)) {
            return $this->cache = [];
        }

        foreach (File::allFiles($root) as $file) {
            if ($file->getExtension() !== 'md' || in_array($file->getFilename(), self::SKIP, true)) {
                continue;
            }

            $fact = $this->parseAndValidate($file->getPathname(), File::get($file->getPathname()));

            if (isset($facts[$fact->factId])) {
                throw new RuntimeException("Semantic corpus: duplicate fact_id '{$fact->factId}' ({$file->getPathname()}).");
            }

            $facts[$fact->factId] = $fact;
        }

        return $this->cache = $facts;
    }

    private function parseAndValidate(string $path, string $contents): SemanticFact
    {
        if (preg_match('/^---\s*\n(.*?)\n---\s*\n?(.*)$/s', $contents, $m) !== 1) {
            throw new RuntimeException("Semantic corpus: missing frontmatter ({$path}).");
        }

        $meta = Yaml::parse($m[1]);
        $meta = is_array($meta) ? $meta : [];
        $body = trim($m[2]);

        $require = function (string $key) use ($meta, $path): mixed {
            if (! array_key_exists($key, $meta) || $meta[$key] === null || $meta[$key] === '') {
                throw new RuntimeException("Semantic corpus: missing '{$key}' ({$path}).");
            }

            return $meta[$key];
        };

        $factId = (string) $require('fact_id');
        $category = (string) $require('category');
        if (! in_array($category, self::CATEGORIES, true)) {
            throw new RuntimeException("Semantic corpus: unknown category '{$category}' ({$path}).");
        }

        $title = (string) $require('title');
        $version = (int) $require('version');
        if ($version < 1) {
            throw new RuntimeException("Semantic corpus: version must be >= 1 ({$path}).");
        }

        $source = in_array($category, self::SOURCE_REQUIRED, true)
            ? (string) $require('source')
            : (string) ($meta['source'] ?? '');

        $validFromRaw = in_array($category, self::VALID_FROM_REQUIRED, true)
            ? $require('valid_from')
            : ($meta['valid_from'] ?? null);

        return new SemanticFact(
            factId: $factId,
            category: $category,
            title: $title,
            source: $source,
            version: $version,
            validFrom: $validFromRaw !== null ? $this->parseDate($validFromRaw)->startOfDay() : null,
            validTo: isset($meta['valid_to']) && $meta['valid_to'] !== null ? $this->parseDate($meta['valid_to'])->endOfDay() : null,
            body: $body,
        );
    }

    /**
     * Parse a date value that may be a string ("2024-04-06") or a Unix timestamp
     * integer (Symfony YAML 1.1 auto-converts bare ISO dates to timestamps).
     */
    private function parseDate(mixed $value): Carbon
    {
        if (is_int($value)) {
            return Carbon::createFromTimestamp($value);
        }

        return Carbon::parse((string) $value);
    }
}
