<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DocumentArticle;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class DocumentArticleFactory extends Factory
{
    protected $model = DocumentArticle::class;

    public function definition(): array
    {
        $title = fake()->sentence(6);

        return [
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1000, 99999),
            'title' => $title,
            'subtitle' => fake()->sentence(10),
            'description' => fake()->paragraph(2),
            'keywords' => implode(',', fake()->words(5)),
            'author_name' => fake()->name(),
            'author_byline' => fake()->name(),
            'cover_image_path' => null,
            'html_body' => '<p>'.fake()->paragraph(5).'</p>',
            'status' => 'draft',
            'published_at' => null,
            'imported_by' => User::factory(),
            'original_filename' => 'sample.docx',
            'original_doc_hash' => hash('sha256', fake()->uuid()),
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => 'published',
            'published_at' => now(),
        ]);
    }
}
