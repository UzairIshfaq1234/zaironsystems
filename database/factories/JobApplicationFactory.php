<?php

namespace Database\Factories;

use App\Models\JobApplication;
use App\Models\JobOpening;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobApplication>
 */
class JobApplicationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'job_opening_id' => JobOpening::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '+92 300 1234567',
            'links' => 'https://github.com/example',
            'message' => fake()->paragraph(),
            'cv_path' => null,
            'cv_name' => null,
            'status' => JobApplication::STATUS_NEW,
            'ip_address' => fake()->ipv4(),
            'user_agent' => 'Mozilla/5.0',
        ];
    }
}
