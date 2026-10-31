<?php

namespace Database\Factories;

use App\Enums\ContentStatus;
use App\Enums\PublicationType;
use App\Models\Publication;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Publication>
 */
class PublicationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = rtrim(fake()->unique()->sentence(6), '.');

        return [
            'site_id' => Site::factory(),
            'author_id' => User::factory(),
            'type' => PublicationType::Research,
            'title' => $title,
            'slug' => Str::slug($title),
            'authors' => fake()->name(),
            'abstract' => fake()->paragraph(),
            'year' => (int) now()->format('Y'),
        ];
    }

    public function status(ContentStatus $status): static
    {
        return $this->state(fn () => [
            'status' => $status,
            'published_at' => $status === ContentStatus::Published ? now()->subMinute() : null,
        ]);
    }
}
