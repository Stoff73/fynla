<?php

declare(strict_types=1);

namespace Database\Factories\Insights;

use App\Models\Insights\InsightTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class InsightTemplateFactory extends Factory
{
    protected $model = InsightTemplate::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->words(3, true),
            'description' => $this->faker->sentence(),
            'body_blocks' => [
                ['type' => 'heading', 'level' => 2, 'text' => 'Section'],
                ['type' => 'paragraph', 'html' => '<p>Template paragraph</p>'],
            ],
            'created_by' => User::factory(),
        ];
    }
}
