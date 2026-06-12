<?php

declare(strict_types=1);

namespace App\Services\AI\Pointers;

/** A handler's result + provenance. Immutable. */
final class FetchResult
{
    /** @param array<string,string> $extra handler-specific provenance fields (e.g. surfaced strategy ids) */
    public function __construct(
        public readonly string $value,        // rendered text injected into context / returned to the tool
        public readonly string $sourceLabel,  // e.g. "TaxConfigService"
        public readonly string $sourceVersion, // source as-of, e.g. the active tax year
        public readonly string $digest,        // short hash of $value for provenance
        public readonly array $extra = [],
    ) {}

    /**
     * Build a result, deriving the digest from the value.
     *
     * @param  array<string,string>  $extra
     */
    public static function make(string $value, string $sourceLabel, string $sourceVersion, array $extra = []): self
    {
        return new self($value, $sourceLabel, $sourceVersion, substr(hash('sha256', $value), 0, 16), $extra);
    }

    /** Provenance tuple for ai_messages.metadata. @return array<string,string> */
    public function provenance(string $pointerId, string $handler): array
    {
        return array_merge([
            'pointer_id' => $pointerId,
            'handler' => $handler,
            'source_label' => $this->sourceLabel,
            'source_version' => $this->sourceVersion,
            'digest' => $this->digest,
        ], $this->extra);
    }
}
