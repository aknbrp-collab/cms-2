<?php

namespace App\Policies;

use App\Contracts\Reviewable;
use App\Enums\ContentStatus;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Policy for site content that goes through the editorial workflow.
 *
 * Abilities combine the module permission ("posts.update") with the
 * user's site scope. Users without the publish permission may only edit
 * or delete content that is still a draft, but may hide or show any of it.
 */
abstract class ContentPolicy
{
    /**
     * The content model this policy is registered for.
     *
     * @return class-string<Reviewable>
     */
    abstract protected function modelClass(): string;

    public function viewAny(User $user): bool
    {
        return $user->can($this->modelClass()::permissionModule().'.view');
    }

    public function view(User $user, Reviewable&Model $content): bool
    {
        return $this->allows($user, $content, 'view');
    }

    /**
     * Content is always created on one specific site.
     */
    public function create(User $user, ?Site $site = null): bool
    {
        $site ??= current_site();

        return $site !== null
            && $user->canAccessSite($site)
            && $user->can($this->modelClass()::permissionModule().'.create');
    }

    public function update(User $user, Reviewable&Model $content): bool
    {
        return $this->allows($user, $content, 'update')
            && ($this->isDraft($content) || $this->allows($user, $content, 'publish'));
    }

    public function delete(User $user, Reviewable&Model $content): bool
    {
        return $this->allows($user, $content, 'delete')
            && ($this->isDraft($content) || $this->allows($user, $content, 'publish'));
    }

    /**
     * Hide from or show on the public site. Unlike update, this is allowed on
     * published content too: it never makes unreviewed content public.
     */
    public function hide(User $user, Reviewable&Model $content): bool
    {
        return $this->allows($user, $content, 'update');
    }

    public function submit(User $user, Reviewable&Model $content): bool
    {
        return $this->allows($user, $content, 'submit');
    }

    /**
     * Approve, reject, publish, schedule, unpublish and archive.
     */
    public function publish(User $user, Reviewable&Model $content): bool
    {
        return $this->allows($user, $content, 'publish');
    }

    protected function allows(User $user, Reviewable&Model $content, string $action): bool
    {
        $site = $content->getRelationValue('site');

        return $site instanceof Site
            && $user->canAccessSite($site)
            && $user->can($content::permissionModule().'.'.$action);
    }

    protected function isDraft(Model $content): bool
    {
        return $content->getAttribute('status') === ContentStatus::Draft;
    }
}
