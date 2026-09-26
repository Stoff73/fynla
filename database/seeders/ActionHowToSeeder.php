<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\TaxActionDefinition;
use Illuminate\Database\Seeder;

/**
 * Loads the how-to steps for action detail cards from their one source,
 * docs/action-how-to/<module>.md. Each entry carries its own status; only an
 * entry CSJ marked `approved` is shown to users (ActionCardService). Re-running
 * is safe: it rewrites steps and status from the file.
 */
class ActionHowToSeeder extends Seeder
{
    /** module => [definition model, column the markdown heading names] */
    private const SOURCES = [
        'tax' => [TaxActionDefinition::class, 'strategy_type'],
    ];

    public function run(): void
    {
        foreach (self::SOURCES as $module => [$model, $column]) {
            $path = base_path("docs/action-how-to/{$module}.md");
            if (! is_file($path)) {
                continue;
            }
            foreach (self::parse((string) file_get_contents($path)) as $key => $entry) {
                $model::query()->where($column, $key)->update([
                    'how_to_steps' => json_encode($entry['steps']),
                    'how_to_status' => $entry['status'],
                ]);
            }
        }
    }

    /**
     * "## key" then "status: draft|approved" then numbered steps.
     *
     * @return array<string, array{status: string, steps: list<string>}>
     */
    public static function parse(string $markdown): array
    {
        $entries = [];
        $key = null;
        foreach (preg_split('/\R/', $markdown) as $line) {
            if (preg_match('/^## ([a-z0-9_]+)\s*$/', $line, $m)) {
                $key = $m[1];
                $entries[$key] = ['status' => 'draft', 'steps' => []];
            } elseif ($key !== null && preg_match('/^status:\s*(draft|approved)\s*$/', $line, $m)) {
                $entries[$key]['status'] = $m[1];
            } elseif ($key !== null && preg_match('/^\d+\.\s+(.+)$/', $line, $m)) {
                $entries[$key]['steps'][] = trim($m[1]);
            }
        }

        return $entries;
    }
}
