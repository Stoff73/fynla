<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\TaxProductReference;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;

/**
 * Generate the `product` semantic corpus from tax_product_reference rows. One
 * .md per active row, fact_id = product-{category}-{type}-{aspect}. Idempotent:
 * clears existing product/*.md first so a re-run reflects the table exactly.
 * Narrative only — no numeric tax values (Rule #3).
 */
final class FynSemanticGenerateProducts extends Command
{
    protected $signature = 'fyn:semantic:generate-products';

    protected $description = 'Generate the product semantic corpus from tax_product_reference.';

    public function handle(): int
    {
        $dir = rtrim((string) config('fyn.memory.semantic_path'), '/').'/product';
        File::ensureDirectoryExists($dir);

        foreach (File::glob($dir.'/*.md') ?: [] as $stale) {
            File::delete($stale);
        }

        $rows = TaxProductReference::query()->where('is_active', true)->orderBy('display_order')->get();
        $count = 0;

        foreach ($rows as $row) {
            $factId = 'product-'.implode('-', array_map(
                static fn (string $p): string => Str::slug((string) $p),
                [$row->product_category, $row->product_type, $row->tax_aspect],
            ));

            $meta = [
                'fact_id' => $factId,
                'category' => 'product',
                'title' => (string) $row->title,
                'source' => 'tax_product_reference#'.$row->id,
                'version' => 1,
            ];

            File::put(
                $dir.'/'.$factId.'.md',
                "---\n".Yaml::dump($meta, 2, 2)."---\n\n".trim((string) $row->summary)."\n",
            );
            $count++;
        }

        $this->info("Generated {$count} product facts → {$dir}");

        return self::SUCCESS;
    }
}
