<?php

namespace App\Livewire;

use App\Models\Site;
use App\Support\Audit;
use Flux\Flux;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Base for admin lists of site reference content with an on/off publish
 * switch (FAQ, leaders, admission periods).
 *
 * Users with "{module}.update" edit unpublished items; changing a published
 * item or the publish switch needs "{module}.publish".
 */
abstract class ReferenceList extends Component
{
    /**
     * Permission module, e.g. "faqs".
     */
    abstract protected function module(): string;

    public function togglePublished(string $id): void
    {
        $item = $this->findItem($id);
        $this->authorizeItem($item, 'publish');

        $published = ! $item->getAttribute('is_published');
        $item->forceFill(['is_published' => $published])->save();

        Audit::record($published ? 'reference.published' : 'reference.unpublished', $item);
        Flux::toast(variant: 'success', text: $published ? __('Published.') : __('Unpublished.'));
    }

    /**
     * Find an item of the current site (or any accessible site in "all sites" mode).
     */
    abstract protected function findItem(string $id): Model;

    protected function authorizeItem(Model $item, string $action): void
    {
        $user = Auth::user();
        $site = $item->getRelationValue('site') ?? Site::query()->find($item->getAttribute('site_id'));
        $module = $this->module();

        $allowed = $site instanceof Site
            && $user->canAccessSite($site)
            && $user->can("{$module}.{$action}")
            && ($action === 'publish' || ! $item->getAttribute('is_published') || $user->can("{$module}.publish"));

        if (! $allowed) {
            throw new AuthorizationException(__('You are not allowed to do this.'));
        }
    }

    protected function authorizeCreate(): Site
    {
        $site = current_site();

        if ($site === null || ! Auth::user()->canAccessSite($site) || ! Auth::user()->can($this->module().'.create')) {
            throw new AuthorizationException(__('Choose a site you manage first.'));
        }

        return $site;
    }
}
