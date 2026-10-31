<?php

namespace Database\Factories;

use App\Enums\BlockType;
use App\Enums\ContentStatus;
use App\Models\Page;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = Str::title(rtrim(fake()->unique()->sentence(3), '.'));

        return [
            'site_id' => Site::factory(),
            'author_id' => User::factory(),
            'title' => $title,
            'slug' => Str::slug($title),
            'blocks' => [
                ['id' => (string) Str::uuid(), 'type' => BlockType::RichText->value, 'data' => ['html' => '<p>'.fake()->paragraph().'</p>']],
            ],
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
