<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Define the model's default state. The app authenticates via Discord OAuth only, so there
     * is no email/password; a unique discord_id stands in as the identity.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'discord_id' => (string) fake()->unique()->numerify('##################'),
            'remember_token' => Str::random(10),
        ];
    }
}
