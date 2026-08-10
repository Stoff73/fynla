<?php

declare(strict_types=1);

namespace App\Services\AI\ContextualConversation;

final readonly class ContextualResource
{
    /**
     * @param  array<string, mixed>  $canonicalFacts
     */
    public function __construct(
        public string $resourceType,
        public ?int $resourceId,
        public string $label,
        public string $overviewScreen,
        public array $canonicalFacts,
    ) {}
}
