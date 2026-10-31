<?php

namespace Database\Factories;

use App\Enums\SiteKey;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Site>
 */
class SiteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return $this->forKey(SiteKey::Pondok);
    }

    /**
     * Indicate that the site is the Pondok Pesantren site.
     */
    public function pondok(): static
    {
        return $this->state(fn () => $this->forKey(SiteKey::Pondok));
    }

    /**
     * Indicate that the site is the Ma'had Aly site.
     */
    public function mahad(): static
    {
        return $this->state(fn () => $this->forKey(SiteKey::Mahad));
    }

    /**
     * @return array<string, mixed>
     */
    protected function forKey(SiteKey $key): array
    {
        return [
            'key' => $key,
            'name' => config("cms.sites.{$key->value}.name"),
            'domain' => config("cms.sites.{$key->value}.domain"),
            'is_active' => true,
        ];
    }
}
