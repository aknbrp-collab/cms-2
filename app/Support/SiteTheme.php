<?php

namespace App\Support;

use App\Enums\MenuLocation;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Site;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

/**
 * Resolves theme views and shared layout data for the current public site.
 *
 * A view named "posts.show" renders "sites.{key}.posts.show" when the site's
 * theme overrides it, otherwise the shared "sites.shared.posts.show".
 */
class SiteTheme
{
    public function __construct(protected CurrentSite $currentSite) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function view(string $name, array $data = []): View
    {
        return view($this->resolve($name), ['site' => $this->site(), ...$data]);
    }

    /**
     * @return view-string
     */
    public function resolve(string $name): string
    {
        foreach (["sites.{$this->site()->key->value}.{$name}", "sites.shared.{$name}"] as $view) {
            if (view()->exists($view)) {
                return $view;
            }
        }

        throw new InvalidArgumentException("Theme view [{$name}] does not exist.");
    }

    /**
     * Top-level items of a menu with their children, in order. Hidden items
     * (with their sub-links) and links to hidden or unpublished targets are left out.
     *
     * @return Collection<int, MenuItem>
     */
    public function menu(MenuLocation $location): Collection
    {
        $menu = Menu::query()->forSite($this->site())->where('location', $location->value)->first();

        if ($menu === null) {
            return new Collection;
        }

        return $menu->rootItems()
            ->visible()
            ->with(['linkable', 'children' => fn ($query) => $query->visible(), 'children.linkable'])
            ->get()
            ->filter(fn (MenuItem $item) => $item->targetIsPublic())
            ->each(fn (MenuItem $item) => $item->setRelation('children', $item->children->filter(fn (MenuItem $child) => $child->targetIsPublic())->values()))
            ->values();
    }

    /**
     * Today's prayer times and Hijri date when enabled in site settings.
     *
     * @return array{hijri: string, times: array<string, string>, offset: int}|null
     */
    public function prayer(): ?array
    {
        $site = $this->site();

        if (! $site->setting('prayer.enabled') || ! is_numeric($site->setting('prayer.latitude')) || ! is_numeric($site->setting('prayer.longitude'))) {
            return null;
        }

        $today = now();

        return Cache::remember("prayer:{$site->id}:{$today->toDateString()}:".md5((string) json_encode($site->setting('prayer'))), $today->endOfDay(), function () use ($site, $today) {
            $times = (new PrayerTimes(
                (float) $site->setting('prayer.latitude'),
                (float) $site->setting('prayer.longitude'),
                (string) $site->setting('prayer.method'),
            ))->forDate($today);

            unset($times['sunrise']);

            return [
                'hijri' => HijriDate::format($today),
                'times' => collect($times)->mapWithKeys(fn (string $time, string $key) => [PrayerTimes::NAMES[$key] => $time])->all(),
                'offset' => (int) ($today->getOffset() / 60),
            ];
        });
    }

    public function site(): Site
    {
        return $this->currentSite->get() ?? abort(404);
    }
}
