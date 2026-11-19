<?php

namespace Database\Seeders;

use App\Enums\SiteKey;
use App\Models\Site;
use App\Support\CurrentSite;
use Database\Seeders\Concerns\AddsHeaderMenuItems;
use Illuminate\Database\Seeder;

/**
 * Pondok "Alumni" header dropdown (v3): Data and Testimoni, placed before
 * "Kontak". Safe to run more than once.
 */
class AlumniMenuSeeder extends Seeder
{
    use AddsHeaderMenuItems;

    public function run(): void
    {
        $site = Site::query()->where('key', SiteKey::Pondok->value)->first();

        if (! $site) {
            return;
        }

        app(CurrentSite::class)->using($site, fn () => $this->addHeaderDropdown($site, 'Alumni', [
            ['Data', '/alumni'],
            ['Testimoni', '/alumni/testimoni'],
        ], before: 'Kontak'));
    }
}
