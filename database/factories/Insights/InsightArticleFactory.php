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
        $title = $this->faker->sentence(6);

        return [
            'slug' => Str::slug($title).'-'.$this->faker->unique()->randomNumber(4),
            'title' => $title,
            'subtitle' => $this->faker->sentence(10),
            'summary' => $this->faker->paragraph(2),
            'category' => $this->faker->randomElement([
                'tax-changes', 'pensions', 'savings-isa', 'estate-planning', 'platform-updates',
            ]),
            'tags' => [$this->faker->word(), $this->faker->word()],
            'body_blocks' => [
                ['type' => 'heading', 'level' => 2, 'text' => 'Overview'],
                ['type' => 'paragraph', 'html' => '<p>'.$this->faker->paragraph().'</p>'],
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
