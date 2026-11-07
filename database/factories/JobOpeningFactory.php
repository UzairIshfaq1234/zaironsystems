<?php

namespace Database\Factories;

use App\Models\JobOpening;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobOpening>
 */
class JobOpeningFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->unique()->jobTitle(),
            'team' => fake()->randomElement(['Engineering', 'AI', 'Design']),
            'type' => 'Full-time',
            'location' => 'Lahore / Remote',
            'description' => fake()->paragraph(),
            'status' => JobOpening::STATUS_OPEN,
            'sort_order' => 0,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn () => ['status' => JobOpening::STATUS_CLOSED]);
    }
}
