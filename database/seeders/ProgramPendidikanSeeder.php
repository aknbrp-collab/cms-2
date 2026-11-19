<?php

namespace Database\Seeders;

use App\Enums\SiteKey;
use App\Models\EducationUnit;
use App\Models\Site;
use App\Support\CurrentSite;
use Database\Seeders\Concerns\AddsHeaderMenuItems;
use Illuminate\Database\Seeder;

/**
 * Pondok "Program Pendidikan" (v3): the Tahfidz, PDF Wustho and PDF Ulya
 * units and a header dropdown linking to them, plus Ma'had Aly (link set
 * later by the admin). Safe to run more than once: existing units and an
 * existing dropdown are left as they are.
 */
class ProgramPendidikanSeeder extends Seeder
{
    use AddsHeaderMenuItems;

    public const UNITS = ['tahfidz' => 'Tahfidz', 'pdf-wustho' => 'PDF Wustho', 'pdf-ulya' => 'PDF Ulya'];

    public const MENU_LABEL = 'Program Pendidikan';

    public function run(): void
    {
        $site = Site::query()->where('key', SiteKey::Pondok->value)->first();

        if (! $site) {
            return;
        }

        app(CurrentSite::class)->using($site, function () use ($site) {
            foreach (array_keys(self::UNITS) as $order => $slug) {
                $unit = EducationUnit::query()->firstOrCreate(
                    ['site_id' => $site->id, 'slug' => $slug],
                    ['name' => self::UNITS[$slug], 'sort_order' => $order],
                );

                if ($unit->wasRecentlyCreated) {
                    $unit->forceFill(['is_published' => true])->save();
                }
            }

            $this->addHeaderDropdown($site, self::MENU_LABEL, [
                ...array_map(fn (string $slug) => [self::UNITS[$slug], "/program-pendidikan/{$slug}"], array_keys(self::UNITS)),
                ["Ma'had Aly", '#'],
            ], after: 'Profil');
        });
    }
}
