<?php

namespace Database\Seeders;

use App\Enums\MenuItemType;
use App\Enums\MenuLocation;
use App\Enums\SiteKey;
use App\Models\EducationUnit;
use App\Models\Menu;
use App\Models\Site;
use App\Support\CurrentSite;
use Illuminate\Database\Seeder;

/**
 * Pondok "Program Pendidikan" (v3): the Tahfidz, PDF Wustho and PDF Ulya
 * units and a header dropdown linking to them, plus Ma'had Aly (link set
 * later by the admin). Safe to run more than once: existing units and an
 * existing dropdown are left as they are.
 */
class ProgramPendidikanSeeder extends Seeder
{
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

            $this->menu($site);
        });
    }

    /**
     * Add the dropdown after "Profil" in the header menu (or at the end).
     */
    protected function menu(Site $site): void
    {
        $menu = Menu::for($site, MenuLocation::Header);
        $topLevel = $menu->items()->whereNull('parent_id');

        if ((clone $topLevel)->where('label', self::MENU_LABEL)->exists()) {
            return;
        }

        $profil = (clone $topLevel)->where('label', 'Profil')->first();
        $position = $profil ? $profil->sort_order + 1 : (int) (clone $topLevel)->max('sort_order') + 1;
        (clone $topLevel)->where('sort_order', '>=', $position)->increment('sort_order');

        $parent = $menu->items()->create([
            'label' => self::MENU_LABEL,
            'type' => MenuItemType::Url,
            'url' => '#',
            'sort_order' => $position,
        ]);

        $children = [
            ...array_map(fn (string $slug) => [self::UNITS[$slug], "/program-pendidikan/{$slug}"], array_keys(self::UNITS)),
            ["Ma'had Aly", '#'],
        ];

        foreach ($children as $order => [$label, $url]) {
            $menu->items()->create([
                'parent_id' => $parent->id,
                'label' => $label,
                'type' => MenuItemType::Url,
                'url' => $url,
                'sort_order' => $order,
            ]);
        }

    }
}
