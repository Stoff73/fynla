<?php

declare(strict_types=1);

namespace Tests\Support\Fyn;

/**
 * The one SSE frame parser for streamed Fyn responses in tests. Each
 * `data: {...}` chunk of `$response->streamedContent()` becomes one array.
 */
final class Sse
{
    /** @return array<int, array<string, mixed>> */
    public static function frames(string $raw): array
    {
        $frames = [];
        foreach (explode("\n\n", $raw) as $chunk) {
            $chunk = trim($chunk);
            if (! str_starts_with($chunk, 'data:')) {
                continue;
            }
            $decoded = json_decode(preg_replace('/^data:\s*/', '', $chunk), true);
            if (is_array($decoded)) {
                $frames[] = $decoded;
            }
        }

        return $frames;
    }
}
