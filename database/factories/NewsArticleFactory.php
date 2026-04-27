<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\News\NewsArticle;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<NewsArticle>
 */
class NewsArticleFactory extends Factory
{
    protected $model = NewsArticle::class;

    public function definition(): array
    {
        $title = $this->faker->sentence(6);

        return [
            'slug'             => Str::slug($title).'-'.$this->faker->unique()->numberBetween(1000, 9999),
            'title'            => rtrim($title, '.'),
            'summary'          => $this->faker->sentence(20),
            'body'             => $this->faker->paragraphs(6, true),
            'image_url'        => null,
            'author_name'      => 'The Fynla Team',
            'status'           => 'published',
            'published_at'     => now(),
            'meta_title'       => null,
            'meta_description' => null,
        ];
    }

    public function draft(): self
    {
        return $this->state(fn () => ['status' => 'draft', 'published_at' => null]);
    }

    public function archived(): self
    {
        return $this->state(fn () => ['status' => 'archived']);
    }
}
