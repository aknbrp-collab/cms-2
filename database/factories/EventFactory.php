<?php

namespace Database\Factories;

use App\Enums\ContentStatus;
use App\Models\Event;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = rtrim(fake()->unique()->sentence(4), '.');
        $startsAt = now()->addDays(fake()->numberBetween(1, 30))->setTime(8, 0);

        return [
            'site_id' => Site::factory(),
            'author_id' => User::factory(),
            'title' => $title,
            'slug' => Str::slug($title),
            'summary' => fake()->sentence(12),
            'location' => 'Aula Utama',
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours(3),
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
