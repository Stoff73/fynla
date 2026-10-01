<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ProtectionActionDefinition;
use App\Models\RetirementActionDefinition;
use App\Models\SavingsActionDefinition;
use App\Models\TaxActionDefinition;
use App\Services\Actions\ActionHowTo;
use Illuminate\Database\Seeder;

/**
 * Loads the how-to steps for action detail cards from their one source,
 * database/seeders/data/action-how-to/<module>.md. Each entry carries its own status; only an
 * entry CSJ marked `approved` is shown to users (ActionCardService). Re-running
 * is safe: it rewrites steps and status from the file.
 */
class ActionHowToSeeder extends Seeder
{
    /** module => [definition model, column the markdown heading names] */
    private const SOURCES = [
        'tax' => [TaxActionDefinition::class, 'strategy_type'],
        'savings' => [SavingsActionDefinition::class, 'key'],
        'protection' => [ProtectionActionDefinition::class, 'key'],
        'retirement' => [RetirementActionDefinition::class, 'key'],
    ];

    public function run(): void
    {
        foreach (array_keys(self::SOURCES) as $module) {
            $this->loadModule($module);
        }
    }

    /**
     * The one source per module, under database/ so every deploy ships it
     * (docs/ is not deployed — deploy/DEPLOY.md).
     */
    public static function sourcePath(string $module): string
    {
        return database_path("seeders/data/action-how-to/{$module}.md");
    }

    public function loadModule(string $module): void
    {
        $path = self::sourcePath($module);
        if (! is_file($path) || ! isset(self::SOURCES[$module])) {
            throw new \RuntimeException("Action how-to source missing for '{$module}': {$path}");
        }
        [$model, $column] = self::SOURCES[$module];
        $entries = self::parse((string) file_get_contents($path));
        // A heading that names no definition would be skipped without a word
        // (a renamed key did exactly that), so it stops the seed instead.
        $unknown = array_diff(array_keys($entries), $model::query()->pluck($column)->all());
        if ($unknown !== []) {
            throw new \RuntimeException("Action how-to headings in {$module}.md match no {$column}: ".implode(', ', $unknown));
        }
        foreach ($entries as $key => $entry) {
            $model::query()->where($column, $key)->update([
                'how_to_steps' => json_encode($entry['steps']),
                'how_to_status' => $entry['status'],
            ]);
        }
    }

    /**
     * @return array<string, array{status: string, steps: list<array{when: string|null, text: string}>}>
     */
    public static function parse(string $markdown): array
    {
        return ActionHowTo::parse($markdown);
    }
}
