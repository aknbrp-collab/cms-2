@props(['content'])

@php
    use App\Enums\ContentStatus;

    $status = $content?->status ?? ContentStatus::Draft;
    $canSubmit = $content && auth()->user()->can('submit', $content);
    $canPublish = $content
        ? auth()->user()->can('publish', $content)
        : false;
@endphp

{{-- Status and workflow actions for the editor sidebar. Methods come from App\Livewire\ContentEditor. --}}
<flux:card class="space-y-4">
    <div class="flex items-center justify-between">
        <flux:heading>{{ __('Status') }}</flux:heading>
        <flux:badge size="sm" :color="$status->color()">{{ $status->label() }}</flux:badge>
    </div>

    @if ($content?->isScheduled())
        <flux:callout icon="clock" color="sky" :text="__('Scheduled for :date', ['date' => $content->publish_at->translatedFormat('d M Y H:i')])" />
    @elseif ($content?->published_at && $status === ContentStatus::Published)
        <flux:text size="sm">{{ __('Published :date', ['date' => $content->published_at->translatedFormat('d M Y H:i')]) }}</flux:text>
    @endif

    @if ($content?->review_note)
        <flux:callout
            :icon="$status === ContentStatus::Draft ? 'exclamation-triangle' : 'chat-bubble-left'"
            :color="$status === ContentStatus::Draft ? 'amber' : 'zinc'"
            :heading="__('Reviewer note')"
            :text="$content->review_note"
        />
    @endif

    <div class="flex flex-col gap-2">
        @if ($status === ContentStatus::Draft && $canSubmit && ! $canPublish)
            <flux:button variant="primary" icon="paper-airplane" wire:click="submitForReview">{{ __('Save & submit for review') }}</flux:button>
        @endif

        @if ($canPublish && $status === ContentStatus::UnderReview)
            <flux:modal.trigger name="workflow-approve">
                <flux:button variant="primary" icon="check">{{ __('Approve') }}</flux:button>
            </flux:modal.trigger>
            <flux:modal.trigger name="workflow-reject">
                <flux:button icon="arrow-uturn-left">{{ __('Request changes') }}</flux:button>
            </flux:modal.trigger>
        @elseif ($canPublish && in_array($status, [ContentStatus::Draft, ContentStatus::Approved], true))
            <flux:modal.trigger name="workflow-publish">
                <flux:button variant="primary" icon="globe-alt">{{ $status === ContentStatus::Approved ? __('Publish / reschedule') : __('Save & publish') }}</flux:button>
            </flux:modal.trigger>
        @endif

        @if ($canPublish && in_array($status, [ContentStatus::Published, ContentStatus::Approved], true))
            <flux:button icon="eye-slash" wire:click="unpublish" wire:confirm="{{ __('Take this offline and move it back to draft?') }}">{{ __('Unpublish') }}</flux:button>
        @endif

        @if ($canPublish && in_array($status, [ContentStatus::Draft, ContentStatus::Approved, ContentStatus::Published], true))
            <flux:button variant="ghost" icon="archive-box" wire:click="archive" wire:confirm="{{ __('Archive this item?') }}">{{ __('Archive') }}</flux:button>
        @endif

        @if ($canPublish && $status === ContentStatus::Archived)
            <flux:button icon="arrow-path" wire:click="restoreFromArchive">{{ __('Restore to draft') }}</flux:button>
        @endif

        @if ($status === ContentStatus::UnderReview && ! $canPublish)
            <flux:text size="sm">{{ __('Waiting for an editor to review. Editing is locked until then.') }}</flux:text>
        @endif
    </div>

    <flux:error name="status" />

    <flux:modal name="workflow-approve" class="md:w-96">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Approve') }}</flux:heading>
            <flux:input type="datetime-local" wire:model="publishAt" :label="__('Publish at')" :description="__('Leave empty to publish now.')" />
            <flux:textarea wire:model="reviewNote" :label="__('Note for the author (optional)')" rows="3" />
            <div class="flex justify-end"><flux:button variant="primary" wire:click="approve">{{ __('Approve') }}</flux:button></div>
        </div>
    </flux:modal>

    <flux:modal name="workflow-reject" class="md:w-96">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Request changes') }}</flux:heading>
            <flux:textarea wire:model="reviewNote" :label="__('What should be changed?')" rows="4" />
            <div class="flex justify-end"><flux:button variant="danger" wire:click="reject">{{ __('Send back') }}</flux:button></div>
        </div>
    </flux:modal>

    <flux:modal name="workflow-publish" class="md:w-96">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Publish') }}</flux:heading>
            <flux:input type="datetime-local" wire:model="publishAt" :label="__('Publish at')" :description="__('Leave empty to publish now.')" />
            <div class="flex justify-end"><flux:button variant="primary" wire:click="publishNow">{{ __('Publish') }}</flux:button></div>
        </div>
    </flux:modal>
</flux:card>
