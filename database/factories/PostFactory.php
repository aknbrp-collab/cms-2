<?php

namespace Database\Factories;

use App\Enums\ContentStatus;
use App\Enums\PostType;
use App\Models\Post;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(6);

        return [
            'site_id' => Site::factory(),
            'type' => PostType::News,
            'author_id' => User::factory(),
            'title' => $title,
            'slug' => Str::slug($title),
            'excerpt' => fake()->paragraph(),
            'body' => '<p>'.fake()->paragraph().'</p>',
        ];
    }

    public function status(ContentStatus $status): static
    {
        return $this->state(fn () => [
            'status' => $status,
            'submitted_at' => $status === ContentStatus::Draft ? null : now(),
            'published_at' => $status === ContentStatus::Published ? now()->subMinute() : null,
        ]);
    }
}
