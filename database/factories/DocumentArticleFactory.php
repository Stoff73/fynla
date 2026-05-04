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
        $title = $this->faker->sentence(6);

        return [
            'slug' => Str::slug($title).'-'.$this->faker->unique()->numberBetween(1000, 99999),
            'title' => $title,
            'subtitle' => $this->faker->sentence(10),
            'description' => $this->faker->paragraph(2),
            'keywords' => implode(',', $this->faker->words(5)),
            'author_name' => $this->faker->name(),
            'author_byline' => $this->faker->name(),
            'cover_image_path' => null,
            'html_body' => '<p>'.$this->faker->paragraph(5).'</p>',
            'status' => 'draft',
            'published_at' => null,
            'imported_by' => User::factory(),
            'original_filename' => 'sample.docx',
            'original_doc_hash' => hash('sha256', $this->faker->uuid()),
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
