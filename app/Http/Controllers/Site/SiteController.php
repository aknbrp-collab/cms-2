<?php

namespace App\Http\Controllers\Site;

use App\Enums\GalleryType;
use App\Enums\PostType;
use App\Http\Controllers\Controller;
use App\Models\Achievement;
use App\Models\AdmissionPeriod;
use App\Models\Category;
use App\Models\Event;
use App\Models\GalleryItem;
use App\Models\Leader;
use App\Models\Page;
use App\Models\Post;
use App\Models\Program;
use App\Models\Publication;
use App\Models\Site;
use App\Support\Seo;
use App\Support\SiteTheme;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

/**
 * Public pages of the current site (resolved by App\Http\Middleware\ResolveSite).
 *
 * Content is looked up here rather than with route model binding because
 * bindings resolve before the site middleware runs.
 */
class SiteController extends Controller
{
    public function __construct(protected SiteTheme $theme) {}

    public function home(): View
    {
        $site = $this->site();
        $latest = $this->postsQuery()->limit(7)->get();

        return $this->theme->view('home', [
            'seo' => Seo::forSite($site),
            'featured' => $this->postsQuery()->where('is_featured', true)->first() ?? $latest->first(),
            'latest' => $latest,
            'announcements' => $this->postsQuery()->where('type', PostType::Announcement->value)->limit(5)->get(),
            'homeCategories' => Category::query()
                ->where('show_on_home', true)
                ->orderBy('home_order')
                ->get()
                ->each(fn (Category $category) => $category->setRelation(
                    'posts',
                    $this->postsQuery()->where('category_id', $category->id)->limit(4)->get(),
                ))
                ->filter(fn (Category $category) => $category->posts->isNotEmpty()),
            'events' => $site->hasModule('events')
                ? Event::query()->published()->upcoming()->orderBy('starts_at')->limit(3)->get()
                : collect(),
            'videos' => $site->hasModule('galleries')
                ? GalleryItem::query()
                    ->whereNotNull('video_url')
                    ->whereHas('gallery', fn (Builder $query) => $query->published()->where('type', GalleryType::Video->value))
                    ->with('gallery')
                    ->latest()
                    ->limit(3)
                    ->get()
                : collect(),
            'leaders' => $site->hasModule('leaders')
                ? Leader::query()->published()->with('photo.media')->orderBy('sort_order')->limit(3)->get()
                : collect(),
            'admission' => $site->hasModule('admissions')
                ? AdmissionPeriod::query()->open()->orderBy('closes_on')->first()
                : null,
            'achievements' => $site->hasModule('achievements')
                ? Achievement::query()->published()->with('image.media')->orderByDesc('achieved_on')->limit(4)->get()
                : collect(),
            'programs' => $site->hasModule('programs')
                ? Program::query()->published()->orderBy('sort_order')->limit(3)->get()
                : collect(),
            'publications' => $site->hasModule('publications')
                ? Publication::query()->published()->with('journal')->latest('published_at')->limit(3)->get()
                : collect(),
        ]);
    }

    public function posts(?PostType $type = null): View
    {
        $site = $this->site();
        $title = $type === PostType::Announcement ? __('Pengumuman') : __('Berita & Artikel');

        return $this->theme->view('posts.index', [
            'seo' => Seo::forSite($site, $title),
            'title' => $title,
            'posts' => $this->postsQuery()
                ->when(
                    $type === PostType::Announcement,
                    fn (Builder $query) => $query->where('type', PostType::Announcement->value),
                    fn (Builder $query) => $query->where('type', '!=', PostType::Announcement->value),
                )
                ->paginate(12),
        ]);
    }

    public function announcements(): View
    {
        return $this->posts(PostType::Announcement);
    }

    public function category(string $slug): View
    {
        $site = $this->site();
        $category = Category::query()->where('slug', $slug)->firstOrFail();

        return $this->theme->view('posts.index', [
            'seo' => Seo::forSite($site, $category->name),
            'title' => $category->name,
            'description' => $category->description,
            'posts' => $this->postsQuery()->where('category_id', $category->id)->paginate(12),
        ]);
    }

    public function post(string $slug): View
    {
        $site = $this->site();
        $post = Post::query()->published()->with(['category', 'author', 'featuredImage.media', 'seo.ogImage.media'])->where('slug', $slug)->firstOrFail();

        return $this->theme->view('posts.show', [
            'seo' => Seo::forContent($site, $post),
            'post' => $post,
            'related' => $this->postsQuery()
                ->whereKeyNot($post->id)
                ->when($post->category_id, fn (Builder $query, string $categoryId) => $query->where('category_id', $categoryId))
                ->limit(3)
                ->get(),
        ]);
    }

    public function page(string $slug): View
    {
        $site = $this->site();
        $page = Page::query()->published()->with(['parent', 'seo.ogImage.media'])->where('slug', $slug)->firstOrFail();

        return $this->theme->view('page', [
            'seo' => Seo::forContent($site, $page),
            'page' => $page,
            'siblings' => Page::query()
                ->published()
                ->where('parent_id', $page->parent_id ?? $page->id)
                ->orderBy('sort_order')
                ->get(['id', 'title', 'slug', 'parent_id']),
        ]);
    }

    /**
     * Published posts of the site, newest first, with what cards need.
     *
     * @return Builder<Post>
     */
    protected function postsQuery(): Builder
    {
        return Post::query()
            ->published()
            ->with(['category', 'featuredImage.media'])
            ->latest('published_at');
    }

    protected function site(): Site
    {
        return current_site() ?? abort(404);
    }
}
