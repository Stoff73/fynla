<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Models\DocumentArticle;

class SlugGenerator
{
    public function unique(string $base, ?int $ignoreId = null): string
    {
        $candidate = $base;
        $n = 1;
        while ($this->exists($candidate, $ignoreId)) {
            $n++;
            $candidate = $base.'-'.$n;
        }

        return $candidate;
    }

    private function exists(string $slug, ?int $ignoreId): bool
    {
        $query = DocumentArticle::where('slug', $slug);
        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }
}
