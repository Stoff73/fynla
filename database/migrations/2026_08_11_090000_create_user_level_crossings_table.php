<?php

declare(strict_types=1);

use App\Models\PointAward;
use App\Models\UserGamification;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasIndex('point_awards', 'point_awards_user_id_id_unique')) {
            Schema::table('point_awards', function (Blueprint $table): void {
                $table->unique(['user_id', 'id'], 'point_awards_user_id_id_unique');
            });
        }

        if (! Schema::hasTable('user_level_crossings')) {
            Schema::create('user_level_crossings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->unsignedTinyInteger('level');
                $table->unsignedBigInteger('point_award_id');
                $table->timestamp('reached_at');
                $table->timestamps();
                $table->unique(['user_id', 'level']);
                $table->foreign(['user_id', 'point_award_id'], 'user_level_crossings_owner_award_fk')
                    ->references(['user_id', 'id'])
                    ->on('point_awards')
                    ->cascadeOnDelete();
            });
        }

        // One-time, resumable backfill from pre-marker awards. Chunking bounds
        // memory and insertOrIgnore makes a partially completed deploy safe.
        $thresholds = collect(config('gamification.levels'))
            ->mapWithKeys(static fn (array $definition, int $level): array => [$level => (int) $definition['min_points']])
            ->all();

        UserGamification::query()->where('level', '>', 1)->select(['user_id', 'level'])->orderBy('user_id')
            ->chunkById(200, function ($rows) use ($thresholds): void {
                foreach ($rows as $gamification) {
                    $points = 0;
                    $nextLevel = 2;
                    $markers = [];
                    PointAward::query()->where('user_id', $gamification->user_id)->orderBy('id')
                        ->each(function (PointAward $award) use (&$points, &$nextLevel, &$markers, $thresholds, $gamification): void {
                            $points += $award->points;
                            while ($nextLevel <= $gamification->level && $points >= ($thresholds[$nextLevel] ?? PHP_INT_MAX)) {
                                $markers[] = [
                                    'user_id' => $gamification->user_id,
                                    'level' => $nextLevel++,
                                    'point_award_id' => $award->id,
                                    'reached_at' => $award->created_at,
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ];
                            }
                        });
                    if ($markers !== []) {
                        DB::table('user_level_crossings')->insertOrIgnore($markers);
                    }
                }
            }, 'user_id');
    }

    public function down(): void
    {
        Schema::dropIfExists('user_level_crossings');
        if (Schema::hasTable('point_awards') && Schema::hasIndex('point_awards', 'point_awards_user_id_id_unique')) {
            Schema::table('point_awards', function (Blueprint $table): void {
                $table->dropUnique('point_awards_user_id_id_unique');
            });
        }
    }
};
