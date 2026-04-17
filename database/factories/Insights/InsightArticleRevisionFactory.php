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
            'title' => fake()->sentence(6),
            'subtitle' => null,
            'summary' => fake()->paragraph(),
            'body_blocks' => [],
            'saved_by' => User::factory(),
            'saved_at' => now(),
        ];
    }
}
