<?php

namespace Database\Factories;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Support\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        return [
            'domain' => fake()->unique()->domainName(),
            'niche' => fake()->words(2, true),
            'cms' => fake()->randomElement(['WordPress', 'Ghost', 'Custom', null]),
            'start_date' => fake()->optional()->date(),
            'acquisition_cost_paisa' => fake()->numberBetween(0, 100_000) * Currency::subunits(),
            'status' => ProjectStatus::Setup,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
