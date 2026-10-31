<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Simple on/off publishing for reference content (FAQ, leaders, admissions).
 *
 * @mixin Model
 */
trait HasPublishToggle
{
    public function initializeHasPublishToggle(): void
    {
        $this->mergeCasts(['is_published' => 'boolean']);
        $this->attributes['is_published'] ??= false;
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where($query->qualifyColumn('is_published'), true);
    }
}
