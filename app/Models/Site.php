<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\SiteKey;
use Database\Factories\SiteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property string $id
 * @property SiteKey $key
 * @property string $name
 * @property string $domain
 * @property bool $is_active
 * @property array<string, mixed>|null $settings
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['key', 'name', 'domain', 'is_active', 'settings'])]
class Site extends Model implements HasMedia
{
    /** @use HasFactory<SiteFactory> */
    use Auditable, HasFactory, HasUuids, InteractsWithMedia;

    /**
     * Branding images managed in site settings.
     */
    public const BRANDING_COLLECTIONS = ['logo', 'calligraphy', 'favicon', 'og_default'];

    /**
     * Every setting with its default value. Keys use dot notation.
     *
     * @var array<string, mixed>
     */
    public const SETTING_DEFAULTS = [
        'identity.tagline' => '',
        'identity.description' => '',
        'contact.phone' => '',
        'contact.email' => '',
        'contact.whatsapp' => '',
        'contact.address' => '',
        'contact.map_embed_url' => '',
        'social.facebook' => '',
        'social.instagram' => '',
        'social.youtube' => '',
        'social.tiktok' => '',
        'theme.primary' => '#064E3B',
        'theme.primary_container' => '#047857',
        'theme.secondary' => '#B45309',
        'header.announcement' => '',
        'header.cta_label' => '',
        'header.cta_url' => '',
        'footer.text' => '',
        'analytics.ga_measurement_id' => '',
        'analytics.head_code' => '',
        'analytics.body_code' => '',
        'prayer.enabled' => false,
        'prayer.latitude' => null,
        'prayer.longitude' => null,
        'prayer.method' => 'kemenag',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'key' => SiteKey::class,
            'is_active' => 'boolean',
            'settings' => 'array',
        ];
    }

    public function registerMediaCollections(): void
    {
        foreach (self::BRANDING_COLLECTIONS as $collection) {
            $this->addMediaCollection($collection)->singleFile();
        }
    }

    /**
     * Find a site by its key.
     */
    public static function findByKey(SiteKey $key): ?self
    {
        return static::query()->where('key', $key->value)->first();
    }

    /**
     * Determine if the given content module is enabled for this site.
     */
    public function hasModule(string $module): bool
    {
        return in_array($module, config("cms.sites.{$this->key->value}.modules", []), true);
    }

    /**
     * Read a setting by dot-notation key, falling back to its default.
     */
    public function setting(string $key): mixed
    {
        return Arr::get($this->settings ?? [], $key, self::SETTING_DEFAULTS[$key] ?? null);
    }

    /**
     * Every setting, with defaults filled in, as a flat dot-notation array.
     *
     * @return array<string, mixed>
     */
    public function allSettings(): array
    {
        return array_merge(self::SETTING_DEFAULTS, array_intersect_key(
            Arr::dot($this->settings ?? []),
            self::SETTING_DEFAULTS,
        ));
    }

    /**
     * Replace the settings from a flat dot-notation array of known keys.
     *
     * @param  array<string, mixed>  $values
     */
    public function fillSettings(array $values): static
    {
        $this->settings = Arr::undot(array_intersect_key($values, self::SETTING_DEFAULTS));

        return $this;
    }

    public function brandingUrl(string $collection): string
    {
        return $this->getFirstMediaUrl($collection);
    }
}
