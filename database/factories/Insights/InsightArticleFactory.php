<?php

declare(strict_types=1);

namespace Database\Factories\Insights;

use App\Models\Insights\InsightArticle;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class InsightArticleFactory extends Factory
{
    protected $model = InsightArticle::class;

    public function definition(): array
    {
        $title = fake()->sentence(6);

        return [
            'slug' => Str::slug($title).'-'.fake()->unique()->randomNumber(4),
            'title' => $title,
            'subtitle' => fake()->sentence(10),
            'summary' => fake()->paragraph(2),
            'category' => fake()->randomElement([
                'tax', 'pensions', 'savings-isa', 'estate-planning',
                'financial-planning', 'ai', 'fintech', 'developer', 'international', 'platform-updates',
            ]),
            'tags' => [fake()->word(), fake()->word()],
            'body_blocks' => [
                ['type' => 'heading', 'level' => 2, 'text' => 'Overview'],
                ['type' => 'paragraph', 'html' => '<p>'.fake()->paragraph().'</p>'],
            ],
            'status' => 'draft',
            'is_featured' => false,
            'is_bespoke' => false,
            'author_id' => User::factory(),
        ];
    }

    public function published(): self
    {
        return $this->state(fn () => [
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    public function featured(): self
    {
        return $this->state(fn () => ['is_featured' => true]);
    }

    public function bespoke(string $component = 'StocksSharesIsaUkPage'): self
    {
        return $this->state(fn () => [
            'is_bespoke' => true,
            'bespoke_component' => $component,
            'body_blocks' => [],
        ]);
    }

    public function scheduled(\DateTimeInterface $at): self
    {
        return $this->state(fn () => [
            'status' => 'draft',
            'scheduled_at' => $at,
        ]);
    }
}
