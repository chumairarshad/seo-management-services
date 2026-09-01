<?php

namespace Database\Factories;

use App\Enums\CredentialType;
use App\Models\Credential;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Credential>
 */
class CredentialFactory extends Factory
{
    protected $model = Credential::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'type' => CredentialType::Other,
            'label' => fake()->words(3, true),
            'username' => fake()->userName(),
            'secret' => 'super-secret-password-'.fake()->bothify('??##'),
            'url' => fake()->optional()->url(),
            'expires_on' => fake()->optional()->dateTimeBetween('now', '+60 days')?->format('Y-m-d'),
            'metadata' => [],
        ];
    }
}
