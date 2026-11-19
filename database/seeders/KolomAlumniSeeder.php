<?php

namespace Database\Seeders;

use App\Enums\MenuItemType;
use App\Enums\SiteKey;
use App\Models\Category;
use App\Models\Site;
use App\Support\CurrentSite;
use Database\Seeders\Concerns\AddsHeaderMenuItems;
use Illuminate\Database\Seeder;

/**
 * Pondok "Kolom Alumni" (v3): a post category for articles written by
 * alumni, linked at the end of the "Berita" dropdown. Safe to run more
 * than once.
 */
class KolomAlumniSeeder extends Seeder
{
    use AddsHeaderMenuItems;

    public const NAME = 'Kolom Alumni';

    public function run(): void
    {
        $site = Site::query()->where('key', SiteKey::Pondok->value)->first();

        if (! $site) {
            return;
        }

        app(CurrentSite::class)->using($site, function () use ($site) {
            $category = Category::query()->firstOrCreate(
                ['site_id' => $site->id, 'slug' => 'kolom-alumni'],
                ['name' => self::NAME, 'description' => 'Tulisan dan kabar dari para alumni.', 'show_on_home' => false],
            );

            $this->addHeaderChild($site, 'Berita', self::NAME, [
                'type' => MenuItemType::Category,
                'linkable_type' => 'category',
                'linkable_id' => $category->id,
            ]);
        });
    }
}
