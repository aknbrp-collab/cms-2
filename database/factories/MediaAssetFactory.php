<?php

namespace Database\Factories;

use App\Models\MediaAsset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MediaAsset>
 */
class MediaAssetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_id' => null,
            'title' => fake()->sentence(3),
            'alt' => fake()->sentence(5),
        ];
    }
}
