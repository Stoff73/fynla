<?php

declare(strict_types=1);

use App\Models\TierConfiguration;
use Illuminate\Database\Migrations\Migration;

/**
 * CSJ 2026-09-15: the Free plan holds two properties (a home and a buy to
 * let), matching the two bank accounts and two investment accounts decided
 * the same morning. TierConfigurationSeeder only creates rows, so the live
 * Free row is updated here.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->setFreePropertyCap(2);
    }

    public function down(): void
    {
        $this->setFreePropertyCap(1);
    }

    private function setFreePropertyCap(int $cap): void
    {
        $free = TierConfiguration::query()->where('tier', 'free')->first();
        if ($free === null) {
            return;
        }

        $caps = is_array($free->count_caps) ? $free->count_caps : [];
        $caps['property'] = $cap;
        $free->count_caps = $caps;
        $free->save();
    }
};
