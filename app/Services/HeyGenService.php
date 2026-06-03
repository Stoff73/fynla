<?php

namespace App\Services;

use App\Models\Article;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Stage 4 — HeyGen talking head video generation.
 *
 * Avatar selection: round-robin across the union of:
 *   - HEYGEN_PHOTO_IDENTITIES (custom Photo Avatars)
 *   - HEYGEN_AVATAR_GROUPS (public group IDs — pipeline picks one look within)
 *   - HEYGEN_AVATARS (specific Avatar IDs)
 *
 * Each entry can be paired with a voice via the `id|voice_id` syntax in env.
 */
class HeyGenService
{
    public function generateVideo(Article $article, string $script): PipelineAsset
    {
        $apiKey = config('services.heygen.api_key');
        $avatar = $this->pickAvatar();

        Log::channel('pipeline')->info("HeyGen render starting — avatar: {$avatar['id']}, voice: {$avatar['voice_id']}");

        // Submit video generation request
        $payload = [
            'video_inputs' => [[
                'character' => [
                    'type' => $avatar['type'], // 'avatar' or 'talking_photo'
                    $avatar['type'].'_id' => $avatar['avatar_id'],
                ],
                'voice' => [
                    'type' => 'text',
                    'input_text' => $script,
                    'voice_id' => $avatar['voice_id'],
                ],
            ]],
            'dimension' => ['width' => 1920, 'height' => 1080],
        ];

        $response = Http::timeout(60)
            ->withHeaders(['x-api-key' => $apiKey])
            ->post('https://api.heygen.com/v2/video/generate', $payload);

        if (! $response->successful()) {
            throw new \Exception("HeyGen submit failed: HTTP {$response->status()} — {$response->body()}");
        }

        $videoId = $response->json('data.video_id');

        // Poll until complete (HeyGen typically takes 1-5 min)
        $videoUrl = $this->pollUntilComplete($videoId, $apiKey);

        // Download to local storage
        $outputPath = storage_path("app/output/article-{$article->id}/video_full.mp4");
        @mkdir(dirname($outputPath), 0755, true);
        file_put_contents($outputPath, file_get_contents($videoUrl));

        $article->update([
            'heygen_video_id' => $videoId,
            'heygen_video_url' => $videoUrl,
            'avatar_used' => $avatar['avatar_id'],
        ]);

        return PipelineAsset::create([
            'article_id' => $article->id,
            'kind' => 'video',
            'template_type' => 'video_full',
            'variant' => $avatar['avatar_id'],
            'aspect_ratio' => '16:9',
            'local_path' => $outputPath,
            'public_url' => $videoUrl,
        ]);
    }

    private function pollUntilComplete(string $videoId, string $apiKey, int $timeoutSec = 600): string
    {
        $start = time();
        while (time() - $start < $timeoutSec) {
            $r = Http::withHeaders(['x-api-key' => $apiKey])
                ->get("https://api.heygen.com/v1/video_status.get?video_id={$videoId}");
            $status = $r->json('data.status');
            if ($status === 'completed') {
                return $r->json('data.video_url');
            }
            if ($status === 'failed') {
                throw new \Exception('HeyGen render failed: '.$r->json('data.error.message', 'unknown'));
            }
            sleep(15);
        }
        throw new \Exception("HeyGen render timed out after {$timeoutSec}s");
    }

    private function pickAvatar(): array
    {
        $entries = [];

        foreach (explode(',', config('services.heygen.photo_identities')) as $e) {
            if ($e = trim($e)) {
                [$id, $voice] = $this->splitPair($e);
                $entries[] = ['type' => 'talking_photo', 'avatar_id' => $id, 'voice_id' => $voice, 'id' => "photo:$id"];
            }
        }
        foreach (explode(',', config('services.heygen.avatar_groups')) as $e) {
            if ($e = trim($e)) {
                [$id, $voice] = $this->splitPair($e);
                $entries[] = ['type' => 'avatar', 'avatar_id' => $id, 'voice_id' => $voice, 'id' => "group:$id"];
            }
        }
        foreach (explode(',', config('services.heygen.avatars')) as $e) {
            if ($e = trim($e)) {
                [$id, $voice] = $this->splitPair($e);
                $entries[] = ['type' => 'avatar', 'avatar_id' => $id, 'voice_id' => $voice, 'id' => "avatar:$id"];
            }
        }

        if (empty($entries)) {
            throw new \Exception('No HeyGen avatars configured.');
        }

        // Round-robin via least-recently-used in the articles table
        $usage = collect($entries)->mapWithKeys(fn ($e) => [
            $e['id'] => Article::where('avatar_used', $e['avatar_id'])->count(),
        ]);
        $leastId = $usage->sort()->keys()->first();

        return collect($entries)->firstWhere('id', $leastId);
    }

    private function splitPair(string $entry): array
    {
        if (str_contains($entry, '|')) {
            return explode('|', $entry, 2);
        }

        return [$entry, config('services.heygen.voice_default')];
    }
}
