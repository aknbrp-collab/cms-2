<?php

namespace App\Social;

use App\Enums\SocialProvider;
use App\Models\Site;
use App\Models\SocialAccount;
use App\Models\SocialPostTarget;
use App\Models\User;

/**
 * One OAuth app (Meta or TikTok): connecting accounts and publishing to them.
 */
interface SocialDriver
{
    /**
     * URL to send the user to for authorization.
     */
    public function authorizationUrl(string $state, string $codeVerifier): string;

    /**
     * Exchange the OAuth code and create or update the accounts it grants.
     *
     * @return list<SocialAccount>
     *
     * @throws SocialException
     */
    public function connect(string $code, string $codeVerifier, Site $site, User $user): array;

    /**
     * Publish one target. Returns the result when the platform confirms right
     * away, or a pending result (TikTok) that is checked later.
     *
     * @throws SocialException
     */
    public function publish(SocialPostTarget $target): PublishResult;

    /**
     * Check a publish that was still processing.
     *
     * @throws SocialException
     */
    public function checkStatus(SocialPostTarget $target): PublishResult;

    /**
     * Refresh the account's access token if it expires.
     *
     * @throws SocialException
     */
    public function refresh(SocialAccount $account): void;

    /**
     * Creator settings shown before posting (TikTok requirement). Other
     * providers return an empty array.
     *
     * @return array<string, mixed>
     *
     * @throws SocialException
     */
    public function creatorInfo(SocialAccount $account): array;

    /**
     * @return list<SocialProvider>
     */
    public function providers(): array;
}
