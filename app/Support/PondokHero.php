<?php

namespace App\Support;

use App\Models\Site;

/**
 * Settings of the Pondok home hero ("hero.*" in Site::SETTING_DEFAULTS),
 * shaped for the view in sites/pondok/hero.blade.php.
 */
class PondokHero
{
    /**
     * Material Symbols an admin can pick for a stat. Keep in sync with the
     * icon list in sites/partials/head.blade.php.
     */
    public const ICONS = [
        'verified_user' => 'Perisai (akreditasi)',
        'military_tech' => 'Medali (prestasi)',
        'groups' => 'Orang (santri)',
        'apartment' => 'Gedung (asrama)',
        'menu_book' => 'Kitab',
        'school' => 'Toga (pendidikan)',
        'mosque' => 'Masjid',
        'star' => 'Bintang',
        'public' => 'Bola dunia',
        'calendar_today' => 'Kalender',
    ];

    /**
     * Icon-tile tones per stat position, as in the reference design.
     */
    public const TONES = [
        'bg-primary/10 text-brand',
        'bg-gold-tint text-tertiary-deep',
        'bg-accent-mint text-on-accent-mint',
        'bg-surface-high text-brand',
    ];

    /**
     * Chip colours of the slider labels, repeating per slide.
     */
    public const CHIPS = [
        'bg-primary text-white',
        'bg-emerald text-white',
        'bg-secondary text-on-surface font-bold',
    ];

    public function __construct(protected Site $site) {}

    public function enabled(): bool
    {
        return $this->site->hasModule('home_hero')
            && (bool) $this->site->setting('hero.enabled')
            && filled($this->site->setting('hero.title'));
    }

    public function get(string $key): string
    {
        return trim((string) $this->site->setting("hero.{$key}"));
    }

    /**
     * Where the brochure button points: the uploaded file, else the URL.
     */
    public function brochureUrl(): string
    {
        return $this->site->brandingUrl('hero_brochure') ?: $this->get('brochure_url');
    }

    /**
     * Filled-in stats with their icon and tone.
     *
     * @return list<array{icon: string, title: string, text: string, tone: string}>
     */
    public function stats(): array
    {
        $stats = [];

        foreach (range(1, 4) as $slot) {
            $title = $this->get("stat{$slot}_title");

            if ($title === '') {
                continue;
            }

            $icon = $this->get("stat{$slot}_icon");

            $stats[] = [
                'icon' => array_key_exists($icon, self::ICONS) ? $icon : 'verified_user',
                'title' => $title,
                'text' => $this->get("stat{$slot}_text"),
                'tone' => self::TONES[$slot - 1],
            ];
        }

        return $stats;
    }
}
