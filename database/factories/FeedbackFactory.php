<?php

namespace Database\Factories;

use App\Enums\FeedbackStatus;
use App\Models\Feedback;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Feedback>
 */
class FeedbackFactory extends Factory
{
    protected $model = Feedback::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'author_name' => fake()->name(),
            'rating' => fake()->numberBetween(4, 5),
            'content' => fake()->realText(120),
            'status' => FeedbackStatus::Pending,
            'published_at' => null,
            'reviewed_by' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => FeedbackStatus::Published,
            'published_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => FeedbackStatus::Rejected,
            'published_at' => null,
        ]);
    }
}
