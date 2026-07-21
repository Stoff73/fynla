<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentFactory extends Factory
{
    protected $model = Document::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'original_filename' => fake()->word().'.pdf',
            'stored_filename' => fake()->uuid().'.pdf',
            'disk' => 'local',
            'path' => 'documents/'.fake()->numberBetween(1, 999).'/'.fake()->uuid().'.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => fake()->numberBetween(10_000, 1_000_000),
            'document_type' => Document::TYPE_PENSION_STATEMENT,
            'status' => Document::STATUS_UPLOADED,
        ];
    }
}
