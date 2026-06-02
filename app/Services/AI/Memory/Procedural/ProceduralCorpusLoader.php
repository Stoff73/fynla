<?php

declare(strict_types=1);

namespace App\Services\AI\Memory\Procedural;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;
use Throwable;

/**
 * Loads + validates the procedural-memory corpus
 * (fyn-memory/procedural/{kind}/{module}/*.md). Mirrors SemanticCorpusLoader.
 *
 * Runtime entry is load(): it degrades to the last-good (or empty) corpus and
 * never throws, so a malformed corpus cannot break a chat turn. loadStrict()
 * throws on any validation error and backs the fyn:procedural:validate deploy
 * gate. Cross-request caching is keyed by a directory signature with a re-stat
 * throttle; the swap is atomic because ProceduralCorpus is immutable.
 */
final class ProceduralCorpusLoader
{
    private const KINDS = ['system_prompt_overlay', 'workflow', 'tool_schema', 'fca_block'];

    private const SKIP = ['_TEMPLATE.md', 'README.md'];

    private const CACHE_KEY = 'fyn:procedural:corpus';

    private const SIG_KEY = 'fyn:procedural:corpus:sig';

    private ?ProceduralCorpus $corpus = null;

    private ?string $signature = null;

    private float $lastStatCheck = 0.0;

    /**
     * Runtime entry. Serves the in-memory corpus within the reload interval;
     * otherwise re-stats the corpus dir and reloads only when the signature
     * changed. Degrades to last-good (or empty) and never throws.
     */
    public function load(): ProceduralCorpus
    {
        $now = microtime(true);
        $interval = (int) config('fyn.memory.procedural_reload_interval', 60);

        if ($this->corpus !== null && ($now - $this->lastStatCheck) < $interval) {
            return $this->corpus;
        }
        $this->lastStatCheck = $now;

        // Everything that touches the filesystem or cache is inside the degrade
        // path: signature() stats every .md file, and a file deleted mid-request
        // during a concurrent corpus swap (deploy git pull / rsync) would raise
        // from getMTime(). load() must never break a chat turn — degrade instead.
        try {
            $sig = $this->signature();

            if ($this->corpus !== null && $this->signature === $sig) {
                return $this->corpus;
            }

            // Cold instance: try the cross-request cache before re-parsing.
            if ($this->corpus === null) {
                $cached = Cache::get(self::CACHE_KEY);
                if ($cached instanceof ProceduralCorpus && Cache::get(self::SIG_KEY) === $sig) {
                    $this->signature = $sig;

                    return $this->corpus = $cached;
                }
            }

            $fresh = $this->parse();
        } catch (Throwable $e) {
            report($e);

            return $this->corpus ?? ($this->corpus = new ProceduralCorpus([]));
        }

        // Atomic swap — ProceduralCorpus is immutable, so replacing the reference is safe.
        $this->corpus = $fresh;
        $this->signature = $sig;
        Cache::put(self::CACHE_KEY, $fresh);
        Cache::put(self::SIG_KEY, $sig);

        return $this->corpus;
    }

    /** Signature of the corpus on disk: max mtime over .md files + file count. */
    private function signature(): string
    {
        $root = (string) config('fyn.memory.procedural_path');
        if (! File::isDirectory($root)) {
            return '0:0';
        }

        $maxMtime = 0;
        $count = 0;
        foreach (self::KINDS as $kind) {
            $kindDir = $root.'/'.$kind;
            if (! File::isDirectory($kindDir)) {
                continue;
            }
            foreach (File::allFiles($kindDir) as $file) {
                if ($file->getExtension() !== 'md' || in_array($file->getFilename(), self::SKIP, true)) {
                    continue;
                }
                $maxMtime = max($maxMtime, $file->getMTime());
                $count++;
            }
        }

        return $maxMtime.':'.$count;
    }

    /** Strict parse — throws on any validation error. Used only by the deploy gate. */
    public function loadStrict(): ProceduralCorpus
    {
        return $this->parse();
    }

    /** Parse + validate the whole corpus. Throws RuntimeException on the first error. */
    private function parse(): ProceduralCorpus
    {
        $root = (string) config('fyn.memory.procedural_path');
        if (! File::isDirectory($root)) {
            return new ProceduralCorpus([]);
        }

        $procedures = [];
        $seenVersion = [];   // "id@version" => path
        $activeById = [];    // id => path

        foreach (self::KINDS as $kind) {
            $kindDir = $root.'/'.$kind;
            if (! File::isDirectory($kindDir)) {
                continue;
            }

            foreach (File::allFiles($kindDir) as $file) {
                if ($file->getExtension() !== 'md' || in_array($file->getFilename(), self::SKIP, true)) {
                    continue;
                }

                $rel = str_replace('\\', '/', $file->getRelativePath());
                $moduleFromPath = $rel === '' ? '' : explode('/', $rel)[0];

                $proc = $this->parseAndValidate($file->getPathname(), File::get($file->getPathname()), $kind, $moduleFromPath);

                $vk = $proc->procedureId.'@'.$proc->version.'|'.$proc->provider;
                if (isset($seenVersion[$vk])) {
                    throw new RuntimeException("Procedural corpus: duplicate {$proc->procedureId}@{$proc->version} ({$file->getPathname()}).");
                }
                $seenVersion[$vk] = $file->getPathname();

                if ($proc->active) {
                    $activeKey = $proc->procedureId.'|'.$proc->provider;
                    if (isset($activeById[$activeKey])) {
                        throw new RuntimeException("Procedural corpus: multiple active versions for '{$proc->procedureId}' ({$file->getPathname()}).");
                    }
                    $activeById[$activeKey] = $file->getPathname();
                }

                $procedures[] = $proc;
            }
        }

        return new ProceduralCorpus($procedures);
    }

    private function parseAndValidate(string $path, string $contents, string $kindFromPath, string $moduleFromPath): Procedure
    {
        if (preg_match('/^---\s*\n(.*?)\n---\s*\n?(.*)$/s', $contents, $m) !== 1) {
            throw new RuntimeException("Procedural corpus: missing frontmatter ({$path}).");
        }

        $meta = Yaml::parse($m[1]);
        if (! is_array($meta)) {
            throw new RuntimeException("Procedural corpus: frontmatter must be a YAML mapping ({$path}).");
        }

        $body = trim($m[2]);
        if ($body === '') {
            throw new RuntimeException("Procedural corpus: empty body ({$path}).");
        }

        $require = function (string $key) use ($meta, $path): mixed {
            if (! array_key_exists($key, $meta) || $meta[$key] === null || $meta[$key] === '') {
                throw new RuntimeException("Procedural corpus: missing '{$key}' ({$path}).");
            }

            return $meta[$key];
        };

        $procedureId = (string) $require('procedure_id');

        $kind = (string) $require('kind');
        if (! in_array($kind, self::KINDS, true)) {
            throw new RuntimeException("Procedural corpus: unknown kind '{$kind}' ({$path}).");
        }
        if ($kind !== $kindFromPath) {
            throw new RuntimeException("Procedural corpus: frontmatter kind '{$kind}' disagrees with path kind '{$kindFromPath}' ({$path}).");
        }

        $module = (string) $require('module');
        if ($moduleFromPath === '') {
            throw new RuntimeException("Procedural corpus: file must live under {$kind}/{module}/ ({$path}).");
        }
        if ($module !== $moduleFromPath) {
            throw new RuntimeException("Procedural corpus: frontmatter module '{$module}' disagrees with path module '{$moduleFromPath}' ({$path}).");
        }

        $version = (int) $require('version');
        if ($version < 1) {
            throw new RuntimeException("Procedural corpus: version must be >= 1 ({$path}).");
        }

        // active is mandatory and must be a real boolean (false is valid, not "missing").
        if (! array_key_exists('active', $meta) || ! is_bool($meta['active'])) {
            throw new RuntimeException("Procedural corpus: 'active' must be a boolean ({$path}).");
        }
        $active = $meta['active'];

        $provider = isset($meta['provider']) ? (string) $meta['provider'] : 'anthropic';
        if (! in_array($provider, ['anthropic', 'xai'], true)) {
            throw new RuntimeException("Procedural corpus: unknown provider '{$provider}' ({$path}).");
        }

        $effectiveFrom = $this->parseDate($require('effective_from'))->startOfDay();
        $effectiveTo = isset($meta['effective_to']) && $meta['effective_to'] !== null
            ? $this->parseDate($meta['effective_to'])->endOfDay()
            : null;

        return new Procedure(
            procedureId: $procedureId,
            kind: $kind,
            module: $module,
            version: $version,
            active: $active,
            effectiveFrom: $effectiveFrom,
            effectiveTo: $effectiveTo,
            body: $body,
            provider: $provider,
        );
    }

    /** Parse a date that may be an ISO string or a Symfony-YAML-coerced timestamp int. */
    private function parseDate(mixed $value): Carbon
    {
        if ($value === '' || $value === null) {
            throw new RuntimeException('Procedural corpus: date value must not be empty.');
        }
        if (is_int($value)) {
            return Carbon::createFromTimestamp($value);
        }

        return Carbon::parse((string) $value);
    }
}
