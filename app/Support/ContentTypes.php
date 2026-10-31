<?php

namespace App\Support;

use App\Models\Event;
use App\Models\Gallery;
use App\Models\Page;
use App\Models\Post;
use App\Models\Publication;

/**
 * Content types that go through the editorial workflow.
 */
class ContentTypes
{
    /**
     * Used by the review queue, scheduled publishing and the dashboard.
     */
    public const REVIEWABLE = [
        Post::class,
        Page::class,
        Event::class,
        Gallery::class,
        Publication::class,
    ];
}
