<?php

namespace App\Livewire;

use App\Contracts\Reviewable;
use App\Services\ContentWorkflow;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Base for content editors: workflow actions behind the x-admin.workflow-panel component.
 */
abstract class ContentEditor extends Component
{
    public string $reviewNote = '';

    public string $publishAt = '';

    /**
     * The content the actions apply to, or null while it is not saved yet.
     */
    abstract protected function workflowSubject(): (Reviewable&Model)|null;

    /**
     * Persist pending edits before a transition. Return false to abort.
     */
    protected function saveBeforeWorkflow(): bool
    {
        return true;
    }

    /**
     * Site that new content is created on: the admin's current site.
     * "All sites" has none, so the user has to pick one first.
     */
    protected function siteIdForNewContent(): string
    {
        $site = current_site();
        abort_if($site === null, 403, __('Choose a site in the sidebar first.'));

        return $site->id;
    }

    public function submitForReview(ContentWorkflow $workflow): void
    {
        $this->runWorkflow(fn ($content) => $workflow->submit($content, Auth::user()), __('Sent for review.'), save: true);
    }

    public function approve(ContentWorkflow $workflow): void
    {
        $this->runWorkflow(
            fn ($content) => $workflow->approve($content, Auth::user(), $this->parsedPublishAt(), $this->reviewNote ?: null),
            __('Approved.'),
            save: true,
        );
    }

    public function reject(ContentWorkflow $workflow): void
    {
        $this->validate(['reviewNote' => ['required', 'string', 'max:2000']], [], ['reviewNote' => __('note')]);

        $this->runWorkflow(fn ($content) => $workflow->reject($content, Auth::user(), $this->reviewNote), __('Sent back to the author.'));
    }

    public function publishNow(ContentWorkflow $workflow): void
    {
        $publishAt = $this->parsedPublishAt();

        $this->runWorkflow(
            fn ($content) => $workflow->publish($content, Auth::user(), $publishAt),
            $publishAt?->isFuture() ? __('Scheduled.') : __('Published.'),
            save: true,
        );
    }

    public function unpublish(ContentWorkflow $workflow): void
    {
        $this->runWorkflow(fn ($content) => $workflow->unpublish($content, Auth::user()), __('Unpublished.'));
    }

    public function archive(ContentWorkflow $workflow): void
    {
        $this->runWorkflow(fn ($content) => $workflow->archive($content, Auth::user()), __('Archived.'));
    }

    public function restoreFromArchive(ContentWorkflow $workflow): void
    {
        $this->runWorkflow(fn ($content) => $workflow->restore($content, Auth::user()), __('Restored to draft.'));
    }

    /**
     * @param  callable(Reviewable&Model): void  $action
     */
    protected function runWorkflow(callable $action, string $message, bool $save = false): void
    {
        if ($save && ! $this->saveBeforeWorkflow()) {
            return;
        }

        $content = $this->workflowSubject();

        if ($content === null) {
            return;
        }

        $action($content);
        $content->refresh();

        $this->reset('reviewNote', 'publishAt');
        Flux::modals()->close();
        Flux::toast(variant: 'success', text: $message);
    }

    protected function parsedPublishAt(): ?CarbonImmutable
    {
        if ($this->publishAt === '') {
            return null;
        }

        $this->validate(['publishAt' => ['date']], [], ['publishAt' => __('publish date')]);

        return CarbonImmutable::parse($this->publishAt, config('app.timezone'));
    }
}
