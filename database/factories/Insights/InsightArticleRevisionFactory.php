<?php

declare(strict_types=1);

namespace Database\Factories\Insights;

use App\Models\Insights\InsightArticle;
use App\Models\Insights\InsightArticleRevision;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class InsightArticleRevisionFactory extends Factory
{
    protected $model = InsightArticleRevision::class;

    public function definition(): array
    {
        return [
            'article_id' => InsightArticle::factory(),
            'title' => $this->faker->sentence(6),
            'subtitle' => null,
            'summary' => $this->faker->paragraph(),
            'body_blocks' => [],
            'saved_by' => User::factory(),
            'saved_at' => now(),
        ];
    }
}
