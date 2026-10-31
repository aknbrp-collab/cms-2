<?php

namespace App\Policies;

use App\Models\MediaAsset;
use App\Models\User;

/**
 * Shared assets (no site) may only be changed by users who manage every site.
 */
class MediaAssetPolicy
{
    public function update(User $user, MediaAsset $asset): bool
    {
        return $user->can('media.update') && $this->ownsSite($user, $asset);
    }

    public function delete(User $user, MediaAsset $asset): bool
    {
        return $user->can('media.delete') && $this->ownsSite($user, $asset);
    }

    protected function ownsSite(User $user, MediaAsset $asset): bool
    {
        return $asset->site === null
            ? $user->canAccessAllSites()
            : $user->canAccessSite($asset->site);
    }
}
