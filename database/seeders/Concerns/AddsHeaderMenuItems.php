<?php

namespace Database\Seeders\Concerns;

use App\Enums\MenuItemType;
use App\Enums\MenuLocation;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Site;

/**
 * Adds links to a site's header menu without disturbing what the admin has
 * built: nothing is added when an item with the same label already exists.
 */
trait AddsHeaderMenuItems
{
    /**
     * Add a top-level dropdown after the item labelled $after (or before
     * $before, or at the end when neither is found).
     *
     * @param  list<array{0: string, 1: string}>  $children  label, url
     */
    protected function addHeaderDropdown(Site $site, string $label, array $children, ?string $after = null, ?string $before = null): void
    {
        $menu = Menu::for($site, MenuLocation::Header);
        $topLevel = $menu->items()->whereNull('parent_id');

        if ((clone $topLevel)->where('label', $label)->exists()) {
            return;
        }

        $anchorAfter = $after ? (clone $topLevel)->where('label', $after)->first() : null;
        $anchorBefore = $before ? (clone $topLevel)->where('label', $before)->first() : null;
        $position = match (true) {
            $anchorAfter !== null => $anchorAfter->sort_order + 1,
            $anchorBefore !== null => $anchorBefore->sort_order,
            default => (int) (clone $topLevel)->max('sort_order') + 1,
        };
        (clone $topLevel)->where('sort_order', '>=', $position)->increment('sort_order');

        $parent = $menu->items()->create(['label' => $label, 'type' => MenuItemType::Url, 'url' => '#', 'sort_order' => $position]);

        foreach ($children as $order => [$childLabel, $url]) {
            $menu->items()->create([
                'parent_id' => $parent->id,
                'label' => $childLabel,
                'type' => MenuItemType::Url,
                'url' => $url,
                'sort_order' => $order,
            ]);
        }
    }

    /**
     * Add a link at the end of the dropdown labelled $parentLabel, if both
     * the dropdown exists and the link is not there yet.
     *
     * @param  array<string, mixed>  $target  type/url or linkable_type/linkable_id
     */
    protected function addHeaderChild(Site $site, string $parentLabel, string $label, array $target): void
    {
        $menu = Menu::for($site, MenuLocation::Header);
        $parent = $menu->items()->whereNull('parent_id')->where('label', $parentLabel)->first();

        if (! $parent instanceof MenuItem || $parent->children()->where('label', $label)->exists()) {
            return;
        }

        $menu->items()->create([
            'parent_id' => $parent->id,
            'label' => $label,
            'sort_order' => (int) $parent->children()->max('sort_order') + 1,
            ...$target,
        ]);
    }
}
