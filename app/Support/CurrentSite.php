<?php

namespace App\Support;

use App\Models\Site;

/**
 * Holds the site the current request operates on.
 *
 * Public requests resolve it from the host; admin requests from the site
 * switcher. A null site in the admin panel means "all sites".
 */
class CurrentSite
{
    protected ?Site $site = null;

    public function set(?Site $site): void
    {
        $this->site = $site;
    }

    public function get(): ?Site
    {
        return $this->site;
    }

    public function id(): ?string
    {
        return $this->site?->id;
    }

    public function has(): bool
    {
        return $this->site !== null;
    }

    /**
     * Run the callback with the given site as the current site.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public function using(?Site $site, callable $callback): mixed
    {
        $previous = $this->site;
        $this->site = $site;

        try {
            return $callback();
        } finally {
            $this->site = $previous;
        }
    }
}
