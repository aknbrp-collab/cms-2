<?php

namespace App\Concerns;

use App\Models\SeoMeta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * Per-item SEO overrides (title, description, OG image, canonical, noindex).
 *
 * @mixin Model
 */
trait HasSeo
{
    public static function bootHasSeo(): void
    {
        static::forceDeleted(fn (self $model) => $model->seo()->delete());
    }

    /**
     * @return MorphOne<SeoMeta, $this>
     */
    public function seo(): MorphOne
    {
        return $this->morphOne(SeoMeta::class, 'seoable');
    }

    /**
     * Save SEO fields, removing the record when every field is empty.
     *
     * @param  array{meta_title?: ?string, meta_description?: ?string, og_image_id?: ?string, canonical_url?: ?string, noindex?: bool}  $values
     */
    public function saveSeo(array $values): void
    {
        $values = array_map(fn ($value) => $value === '' ? null : $value, $values);

        if (array_filter($values) === []) {
            $this->seo()->delete();

            return;
        }

        $this->seo()->updateOrCreate([], $values);
    }
}
