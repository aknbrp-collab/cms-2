<?php

namespace App\Support;

use App\Models\Event;
use App\Models\Gallery;
use App\Models\MediaAsset;
use App\Models\Page;
use App\Models\Post;
use App\Models\Site;
use Illuminate\Support\Str;

/**
 * Meta tags for one public page: title, description, image, canonical, robots.
 */
readonly class Seo
{
    public function __construct(
        public string $title,
        public string $description = '',
        public string $image = '',
        public string $canonical = '',
        public bool $noindex = false,
        public string $type = 'website',
    ) {}

    /**
     * Site defaults, optionally with a page title.
     */
    public static function forSite(Site $site, ?string $title = null): self
    {
        return new self(
            title: $title ? "{$title} | {$site->name}" : $site->name.self::tagline($site),
            description: (string) $site->setting('identity.description'),
            image: $site->brandingUrl('og_default'),
            canonical: url()->current(),
        );
    }

    /**
     * Meta tags of a content item, using its SEO overrides when present.
     */
    public static function forContent(Site $site, Post|Page|Event|Gallery $content): self
    {
        $seo = $content->seo;
        $excerpt = match (true) {
            $content instanceof Post => (string) $content->excerpt,
            $content instanceof Event => (string) $content->summary,
            $content instanceof Gallery => (string) $content->description,
            default => '',
        };
        $fallbackImage = match (true) {
            $content instanceof Post, $content instanceof Event => $content->featuredImage,
            $content instanceof Gallery => $content->cover(),
            default => null,
        };
        $image = $seo->ogImage ?? $fallbackImage;

        return new self(
            title: ($seo?->meta_title ?: $content->title).' | '.$site->name,
            description: $seo?->meta_description
                ?: ($excerpt !== '' ? $excerpt : Str::limit(strip_tags((string) ($content instanceof Post ? $content->body : '')), 160))
                ?: (string) $site->setting('identity.description'),
            image: $image instanceof MediaAsset ? $image->url('large') : $site->brandingUrl('og_default'),
            canonical: $seo?->canonical_url ?: url()->current(),
            noindex: (bool) $seo?->noindex,
            type: $content instanceof Post ? 'article' : 'website',
        );
    }

    protected static function tagline(Site $site): string
    {
        $tagline = (string) $site->setting('identity.tagline');

        return $tagline !== '' ? " | {$tagline}" : '';
    }
}
