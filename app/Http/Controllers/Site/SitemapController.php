<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function sitemap(): Response
    {
        $notHidden = fn (Builder $query) => $query->whereDoesntHave('seo', fn (Builder $query) => $query->where('noindex', true));

        $urls = collect([['loc' => url('/'), 'lastmod' => null]])
            ->concat(Page::query()->published()->where($notHidden)->get(['slug', 'updated_at'])
                ->map(fn (Page $page) => ['loc' => url($page->slug), 'lastmod' => $page->updated_at]))
            ->concat(Post::query()->published()->where($notHidden)->latest('published_at')->limit(5000)->get(['slug', 'updated_at'])
                ->map(fn (Post $post) => ['loc' => route('site.posts.show', $post->slug), 'lastmod' => $post->updated_at]))
            ->concat(Category::query()->get(['slug', 'updated_at'])
                ->map(fn (Category $category) => ['loc' => route('site.categories.show', $category->slug), 'lastmod' => $category->updated_at]));

        return response()
            ->view('sites.sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml');
    }

    public function robots(): Response
    {
        return response("User-agent: *\nAllow: /\n\nSitemap: ".route('site.sitemap')."\n")
            ->header('Content-Type', 'text/plain');
    }
}
