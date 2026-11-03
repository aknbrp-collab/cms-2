<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * An "is_hidden" switch that removes an item from the public site without
 * touching its other state (workflow status, publish dates). Toggled through
 * App\Support\Visibility.
 *
 * @mixin Model
 */
trait HasVisibility
{
    public function initializeHasVisibility(): void
    {
        $this->mergeCasts(['is_hidden' => 'boolean']);
        $this->attributes['is_hidden'] ??= false;
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeVisible(Builder $query): void
    {
        $query->where($query->qualifyColumn('is_hidden'), false);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeHidden(Builder $query): void
    {
        $query->where($query->qualifyColumn('is_hidden'), true);
    }

    public function isHidden(): bool
    {
        return (bool) $this->getAttribute('is_hidden');
    }
}
