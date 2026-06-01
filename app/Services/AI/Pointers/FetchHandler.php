<?php

declare(strict_types=1);

namespace App\Services\AI\Pointers;

interface FetchHandler
{
    /** Stable id referenced by a pointer's `handler` frontmatter (e.g. 'tax_allowance'). */
    public function id(): string;

    public function fetch(FetchContext $ctx): FetchResult;
}
