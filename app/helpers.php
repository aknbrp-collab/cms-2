<?php

use App\Models\Site;
use App\Support\CurrentSite;

if (! function_exists('current_site')) {
    /**
     * The site the current request operates on, or null for "all sites".
     */
    function current_site(): ?Site
    {
        return app(CurrentSite::class)->get();
    }
}
