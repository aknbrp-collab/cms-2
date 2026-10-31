<?php

namespace Database\Seeders;

use App\Enums\SiteKey;
use App\Models\Site;
use Illuminate\Database\Seeder;

class SiteSeeder extends Seeder
{
    /**
     * Create both public sites from config/cms.php.
     */
    public function run(): void
    {
        foreach (SiteKey::cases() as $key) {
            Site::query()->firstOrCreate(
                ['key' => $key->value],
                [
                    'name' => config("cms.sites.{$key->value}.name"),
                    'domain' => config("cms.sites.{$key->value}.domain"),
                    'is_active' => true,
                ],
            );
        }
    }
}
