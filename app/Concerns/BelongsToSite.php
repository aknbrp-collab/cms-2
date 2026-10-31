<?php

namespace App\Concerns;

use App\Models\Site;
use App\Support\CurrentSite;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Scope a model to the current site.
 *
 * Queries are limited to the current site when one is set, and new records
 * are assigned to it. Use withoutSiteScope() for cross-site queries.
 *
 * @mixin Model
 */
trait BelongsToSite
{
    public static function bootBelongsToSite(): void
    {
        static::addGlobalScope('site', function (Builder $query) {
            $site = app(CurrentSite::class);

            if ($site->has()) {
                $query->where($query->qualifyColumn('site_id'), $site->id());
            }
        });

        static::creating(function (Model $model) {
            if (blank($model->getAttribute('site_id'))) {
                $model->setAttribute('site_id', app(CurrentSite::class)->id());
            }
        });
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * Query across all sites.
     *
     * @return Builder<static>
     */
    public static function withoutSiteScope(): Builder
    {
        return static::query()->withoutGlobalScope('site');
    }

    /**
     * Limit the query to the given site, ignoring the current site.
     *
     * @param  Builder<static>  $query
     */
    public function scopeForSite(Builder $query, Site|string $site): void
    {
        $query->withoutGlobalScope('site')
            ->where($query->qualifyColumn('site_id'), $site instanceof Site ? $site->id : $site);
    }
}
