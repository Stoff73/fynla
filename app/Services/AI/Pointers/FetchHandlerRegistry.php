<?php

declare(strict_types=1);

namespace App\Services\AI\Pointers;

use RuntimeException;

/** The closed whitelist of code-defined fetchers. Markdown pointers reference these by id. */
final class FetchHandlerRegistry
{
    /** @var array<string, FetchHandler> */
    private array $handlers = [];

    /** @param iterable<FetchHandler> $handlers */
    public function __construct(iterable $handlers)
    {
        foreach ($handlers as $h) {
            $this->handlers[$h->id()] = $h;
        }
    }

    public function has(string $id): bool
    {
        return isset($this->handlers[$id]);
    }

    public function get(string $id): FetchHandler
    {
        if (! isset($this->handlers[$id])) {
            throw new RuntimeException("Pointer registry: no registered FetchHandler for '{$id}'.");
        }

        return $this->handlers[$id];
    }

    /** @return list<string> */
    public function ids(): array
    {
        return array_keys($this->handlers);
    }
}
