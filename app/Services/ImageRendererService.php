<?php

namespace App\Services;

use App\Models\Article;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * Stage 3 — Renders the 3 image types (square, story, YouTube thumbnail) by
 * invoking scripts/render_template.py with chosen variants.
 *
 * Variant selection: topic_aware first (matches article topic to variant.topic),
 * falls back to round_robin within type.
 */
class ImageRendererService
{
    public function renderAll(Article $article, array $claudeOutput): array
    {
        $layoutsPath = config('services.templates.layouts');
        $templatesDir = config('services.templates.dir');
        $layouts = json_decode(file_get_contents($layoutsPath), true);

        $assets = [];

        foreach (['square_stat_card', 'story_card', 'youtube_thumbnail'] as $templateType) {
            $variant = $this->pickVariant($layouts[$templateType]['variants'], $article);
            $fields = $claudeOutput['image_overlay_text'][$this->fieldsKey($templateType)];

            $outputPath = storage_path("app/output/article-{$article->id}/{$templateType}.png");
            @mkdir(dirname($outputPath), 0755, true);

            $process = new Process([
                'python3',
                base_path('scripts/render_template.py'),
                '--template-type', $templateType,
                '--variant', $variant['file'],
                '--layouts', $layoutsPath,
                '--templates-dir', $templatesDir,
                '--fields-json', json_encode($fields),
                '--output', $outputPath,
            ]);
            $process->setTimeout(60);
            $process->run();

            if (! $process->isSuccessful()) {
                throw new \Exception("Pillow render failed for {$templateType}: ".$process->getErrorOutput());
            }

            $asset = PipelineAsset::create([
                'article_id' => $article->id,
                'kind' => 'image',
                'template_type' => $templateType,
                'variant' => $variant['file'],
                'aspect_ratio' => $this->aspectRatio($templateType),
                'local_path' => $outputPath,
            ]);

            Log::channel('pipeline')->info("Rendered {$templateType} variant {$variant['file']} → {$outputPath}");
            $assets[] = $asset;
        }

        return $assets;
    }

    private function pickVariant(array $variants, Article $article): array
    {
        $strategy = config('services.templates.variant_strategy', 'topic_aware');

        if ($strategy === 'topic_aware' && $article->inferred_topic) {
            // Try exact topic match first
            $match = collect($variants)->firstWhere('topic', $article->inferred_topic);
            if ($match) {
                return $match;
            }
        }

        // Round-robin: pick the variant whose file appears LEAST often in the assets table
        $usageCounts = collect($variants)->mapWithKeys(function ($v) {
            $count = PipelineAsset::where('variant', $v['file'])->count();

            return [$v['file'] => $count];
        });

        $leastUsedFile = $usageCounts->sort()->keys()->first();

        return collect($variants)->firstWhere('file', $leastUsedFile);
    }

    private function fieldsKey(string $templateType): string
    {
        return match ($templateType) {
            'square_stat_card' => 'square_card',
            'story_card' => 'story_card',
            'youtube_thumbnail' => 'youtube_thumbnail',
        };
    }

    private function aspectRatio(string $templateType): string
    {
        return match ($templateType) {
            'square_stat_card' => '1:1',
            'story_card' => '9:16',
            'youtube_thumbnail' => '16:9',
        };
    }
}
