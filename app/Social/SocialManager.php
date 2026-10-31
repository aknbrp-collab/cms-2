<?php

namespace App\Social;

use App\Enums\SocialProvider;

/**
 * Picks the driver for a provider: the real API when SOCIAL_DRIVER=live and
 * the app credentials are set, otherwise the fake driver.
 */
class SocialManager
{
    public function forProvider(SocialProvider $provider): SocialDriver
    {
        if (! $this->isLive()) {
            return new FakeDriver($provider);
        }

        if (! $this->isConfigured($provider)) {
            throw new SocialException(__(':provider is not configured yet. Add the app keys to .env.', ['provider' => $provider->label()]));
        }

        return $provider->oauthApp() === 'tiktok' ? new TikTokDriver : new MetaDriver;
    }

    /**
     * Driver for an OAuth app ("meta" or "tiktok").
     */
    public function forApp(string $app, ?SocialProvider $fakeProvider = null): SocialDriver
    {
        if (! $this->isLive()) {
            return new FakeDriver($fakeProvider ?? ($app === 'tiktok' ? SocialProvider::TikTok : SocialProvider::Facebook));
        }

        return match ($app) {
            'tiktok' => $this->forProvider(SocialProvider::TikTok),
            'meta' => $this->forProvider(SocialProvider::Facebook),
            default => throw new SocialException('Unknown social app.'),
        };
    }

    public function isLive(): bool
    {
        return config('social.driver') === 'live';
    }

    public function isConfigured(SocialProvider $provider): bool
    {
        return $provider->oauthApp() === 'tiktok'
            ? filled(config('social.tiktok.client_key')) && filled(config('social.tiktok.client_secret'))
            : filled(config('social.meta.app_id')) && filled(config('social.meta.app_secret'));
    }

    /**
     * Whether accounts of this provider can be connected right now.
     */
    public function canConnect(SocialProvider $provider): bool
    {
        return ! $this->isLive() || $this->isConfigured($provider);
    }
}
