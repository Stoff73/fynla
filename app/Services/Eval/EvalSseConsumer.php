<?php

declare(strict_types=1);

namespace App\Services\Eval;

/**
 * Parse a complete Server-Sent Events response body into a list of decoded
 * event payload arrays.
 *
 * The chat send stream uses only `data: ...` frames separated by `\n\n`.
 * Comments (`: keep-alive`) and other SSE fields (`event:`, `id:`, `retry:`)
 * are discarded since the chat protocol does not use them.
 */
final class EvalSseConsumer
{
    /**
     * @return list<array<string, mixed>>
     */
    public function consume(string $body): array
    {
        if ($body === '') {
            return [];
        }

        $events = [];
        $frames = preg_split('/\n\n+/', $body) ?: [];

        foreach ($frames as $frame) {
            $frame = trim($frame);
            if ($frame === '') {
                continue;
            }

            $dataParts = [];
            foreach (explode("\n", $frame) as $line) {
                if (! str_starts_with($line, 'data: ')) {
                    continue;
                }
                $dataParts[] = substr($line, 6);
            }

            if ($dataParts === []) {
                continue;
            }

            $payload = implode("\n", $dataParts);
            $decoded = json_decode($payload, true);
            if (is_array($decoded)) {
                $events[] = $decoded;
            }
        }

        return $events;
    }
}
