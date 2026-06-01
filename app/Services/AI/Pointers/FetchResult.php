<?php

declare(strict_types=1);

namespace App\Services\AI\Pointers;

/** A handler's result + provenance. Immutable. */
final class FetchResult
{
    public function __construct(
        public readonly string $value,        // rendered text injected into context / returned to the tool
        public readonly string $sourceLabel,  // e.g. "TaxConfigService"
        public readonly string $sourceVersion, // source as-of, e.g. the active tax year
        public readonly string $digest,        // short hash of $value for provenance
    ) {}

    /** Build a result, deriving the digest from the value. */
    public static function make(string $value, string $sourceLabel, string $sourceVersion): self
    {
        return new self($value, $sourceLabel, $sourceVersion, substr(hash('sha256', $value), 0, 16));
    }

    /** Provenance tuple for ai_messages.metadata. @return array<string,string> */
    public function provenance(string $pointerId, string $handler): array
    {
        return [
            'pointer_id' => $pointerId,
            'handler' => $handler,
            'source_label' => $this->sourceLabel,
            'source_version' => $this->sourceVersion,
            'digest' => $this->digest,
        ];
    }
}
