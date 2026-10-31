<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::title(rtrim(fake()->unique()->sentence(2), '.'));

        return [
            'site_id' => Site::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'show_on_home' => false,
            'home_order' => 0,
        ];
    }
}
