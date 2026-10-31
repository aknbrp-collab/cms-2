<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Event;
use App\Models\Gallery;
use App\Models\Page;
use App\Models\Post;
use App\Models\Site;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

class SitemapController extends Controller
{
    public function sitemap(): Response
    {
        $site = current_site() ?? abort(404);

        $urls = collect([['loc' => url('/'), 'lastmod' => null]])
            ->concat($this->indexable(Page::query()->published())->get(['slug', 'updated_at'])
                ->map(fn (Page $page) => ['loc' => url($page->slug), 'lastmod' => $page->updated_at]))
            ->concat($this->indexable(Post::query()->published())->latest('published_at')->limit(5000)->get(['slug', 'updated_at'])
                ->map(fn (Post $post) => ['loc' => route('site.posts.show', $post->slug), 'lastmod' => $post->updated_at]))
            ->concat(Category::query()->get(['slug', 'updated_at'])
                ->map(fn (Category $category) => ['loc' => route('site.categories.show', $category->slug), 'lastmod' => $category->updated_at]))
            ->concat($this->moduleUrls($site));

        return response()
            ->view('sites.sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml');
    }

    public function robots(): Response
    {
        return response("User-agent: *\nAllow: /\n\nSitemap: ".route('site.sitemap')."\n")
            ->header('Content-Type', 'text/plain');
    }

    /**
     * Index pages and items of the modules enabled for the site.
     *
     * @return Collection<int, array{loc: string, lastmod: ?CarbonInterface}>
     */
    protected function moduleUrls(Site $site): Collection
    {
        $urls = collect();

        foreach (['events' => 'site.events.index', 'galleries' => 'site.galleries.index', 'faqs' => 'site.faqs', 'leaders' => 'site.leaders', 'admissions' => 'site.admissions.show'] as $module => $route) {
            if ($site->hasModule($module)) {
                $urls->push(['loc' => route($route), 'lastmod' => null]);
            }
        }

        if ($site->hasModule('events')) {
            $urls = $urls->concat($this->indexable(Event::query()->published())->get(['slug', 'updated_at'])
                ->map(fn (Event $event) => ['loc' => route('site.events.show', $event->slug), 'lastmod' => $event->updated_at]));
        }

        if ($site->hasModule('galleries')) {
            $urls = $urls->concat($this->indexable(Gallery::query()->published())->get(['slug', 'updated_at'])
                ->map(fn (Gallery $gallery) => ['loc' => route('site.galleries.show', $gallery->slug), 'lastmod' => $gallery->updated_at]));
        }

        return $urls;
    }

    /**
     * Leave out content marked "noindex" in its SEO settings.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function indexable(Builder $query): Builder
    {
        return $query->whereDoesntHave('seo', fn (Builder $query) => $query->where('noindex', true));
    }
}
