<?php

namespace Database\Factories;

use App\Enums\ContentStatus;
use App\Enums\GalleryType;
use App\Models\Gallery;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Gallery>
 */
class GalleryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = rtrim(fake()->unique()->sentence(3), '.');

        return [
            'site_id' => Site::factory(),
            'author_id' => User::factory(),
            'type' => GalleryType::Photo,
            'title' => $title,
            'slug' => Str::slug($title),
            'taken_on' => now()->subWeek(),
        ];
    }

    public function video(): static
    {
        return $this->state(fn () => ['type' => GalleryType::Video]);
    }

    public function status(ContentStatus $status): static
    {
        return $this->state(fn () => [
            'status' => $status,
            'published_at' => $status === ContentStatus::Published ? now()->subMinute() : null,
        ]);
    }
}
