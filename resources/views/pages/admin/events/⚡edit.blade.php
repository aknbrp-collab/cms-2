<?php

use App\Contracts\Reviewable;
use App\Livewire\ContentEditor;
use App\Models\Event;
use App\Models\Site;
use App\Support\RichText;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;

new #[Title('Edit event')] class extends ContentEditor {
    public ?Event $event = null;

    /** The site this editor works on, fixed when it opens. */
    #[Locked]
    public ?string $siteId = null;

    public string $title = '';

    public string $slug = '';

    public string $label = '';

    public string $summary = '';

    public string $body = '';

    public string $location = '';

    public string $audience = '';

    public string $startsAt = '';

    public string $endsAt = '';

    public bool $allDay = false;

    public ?string $featuredImageId = null;

    /** @var array{meta_title: ?string, meta_description: ?string, og_image_id: ?string, canonical_url: ?string, noindex: bool} */
    public array $seo = ['meta_title' => null, 'meta_description' => null, 'og_image_id' => null, 'canonical_url' => null, 'noindex' => false];

    public function mount(?Event $event = null): void
    {
        if ($event?->exists) {
            $this->authorize('view', $event);
            $this->event = $event;
            $this->siteId = $event->site_id;
            $this->fill([
                'title' => $event->title,
                'slug' => $event->slug,
                'label' => (string) $event->label,
                'summary' => (string) $event->summary,
                'body' => (string) $event->body,
                'location' => (string) $event->location,
                'audience' => (string) $event->audience,
                'startsAt' => $event->starts_at->format('Y-m-d\TH:i'),
                'endsAt' => (string) $event->ends_at?->format('Y-m-d\TH:i'),
                'allDay' => $event->all_day,
                'featuredImageId' => $event->featured_image_id,
            ]);

            if ($event->seo) {
                $this->seo = array_merge($this->seo, $event->seo->only(array_keys($this->seo)));
            }
        } else {
            $this->authorize('create', Event::class);
            $this->siteId = current_site()?->id;
            $this->startsAt = now()->addWeek()->setTime(8, 0)->format('Y-m-d\TH:i');
        }
    }

    #[Computed]
    public function canEdit(): bool
    {
        return $this->event === null || Auth::user()->can('update', $this->event);
    }

    public function save(): void
    {
        $this->event
            ? $this->authorize('update', $this->event)
            : $this->authorize('create', [Event::class, Site::query()->findOrFail($this->siteId)]);

        $this->slug = Str::slug($this->slug ?: $this->title);

        $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'alpha_dash', 'max:255', Rule::unique('events')->where('site_id', $this->siteId)->ignore($this->event?->id)],
            'label' => ['nullable', 'string', 'max:60'],
            'summary' => ['nullable', 'string', 'max:1000'],
            'body' => ['nullable', 'string', 'max:500000'],
            'location' => ['nullable', 'string', 'max:255'],
            'audience' => ['nullable', 'string', 'max:100'],
            'startsAt' => ['required', 'date'],
            'endsAt' => ['nullable', 'date', 'after_or_equal:startsAt'],
            'allDay' => ['boolean'],
            'featuredImageId' => ['nullable', 'exists:media_assets,id'],
            'seo.meta_title' => ['nullable', 'string', 'max:255'],
            'seo.meta_description' => ['nullable', 'string', 'max:500'],
            'seo.og_image_id' => ['nullable', 'exists:media_assets,id'],
            'seo.canonical_url' => ['nullable', 'url', 'max:2048'],
            'seo.noindex' => ['boolean'],
        ]);

        $event = $this->event ?? new Event(['site_id' => $this->siteId, 'author_id' => Auth::id()]);
        $event->fill([
            'title' => $this->title,
            'slug' => $this->slug,
            'label' => $this->label ?: null,
            'summary' => $this->summary ?: null,
            'body' => RichText::clean($this->body),
            'location' => $this->location ?: null,
            'audience' => $this->audience ?: null,
            'starts_at' => CarbonImmutable::parse($this->startsAt),
            'ends_at' => $this->endsAt !== '' ? CarbonImmutable::parse($this->endsAt) : null,
            'all_day' => $this->allDay,
            'featured_image_id' => $this->featuredImageId,
        ])->save();
        $event->saveSeo($this->seo);

        if ($this->event === null) {
            Flux::toast(variant: 'success', text: __('Draft created.'));
            $this->redirectRoute('admin.events.edit', $event, navigate: true);

            return;
        }

        $this->event = $event->refresh();
        Flux::toast(variant: 'success', text: __('Saved.'));
    }

    protected function workflowSubject(): (Reviewable&Model)|null
    {
        return $this->event;
    }

    protected function saveBeforeWorkflow(): bool
    {
        if ($this->canEdit) {
            $this->save();
        }

        return $this->event !== null;
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:link :href="route('admin.events.index')" wire:navigate class="text-sm">← {{ __('Agenda') }}</flux:link>
            <flux:heading size="xl" class="mt-1">{{ $event ? $event->title : __('New event') }}</flux:heading>
            <flux:text class="mt-1">{{ $event?->site->name ?? current_site()?->name }}</flux:text>
        </div>
        @if ($this->canEdit)
            <flux:button variant="primary" wire:click="save">{{ $event ? __('Save') : __('Save draft') }}</flux:button>
        @endif
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="min-w-0 space-y-6 lg:col-span-2">
            @if (! $this->canEdit)
                <flux:callout icon="lock-closed" color="zinc" :text="__('This event is locked while it is under review or published. An editor can unpublish it to allow changes.')" />
            @endif

            <fieldset @disabled(! $this->canEdit) class="space-y-6">
                <flux:card class="space-y-5">
                    <flux:input wire:model="title" :label="__('Title')" required />
                    <flux:input wire:model="slug" :label="__('Slug')" :description="__('Leave empty to generate from the title.')" />
                    <div class="grid gap-5 sm:grid-cols-2">
                        <flux:input wire:model="label" :label="__('Label')" :placeholder="__('e.g. Simposium Ilmiah')" />
                        <flux:input wire:model="audience" :label="__('Audience')" :placeholder="__('e.g. Terbuka untuk umum')" />
                    </div>
                    <flux:textarea wire:model="summary" :label="__('Summary')" rows="2" />
                    @if ($this->canEdit)
                        <x-admin.rich-text model="body" :label="__('Details')" />
                    @else
                        <div class="trix-content">{!! $body !!}</div>
                    @endif
                </flux:card>

                @if ($this->canEdit)
                    <x-admin.seo-fields model="seo" />
                @endif
            </fieldset>
        </div>

        <div class="space-y-6">
            @if ($event)
                <x-admin.workflow-panel :content="$event" />
            @else
                <flux:callout icon="information-circle" :text="__('Save the draft first. Then you can submit it for review or publish it.')" />
            @endif

            <flux:card class="space-y-5">
                <fieldset @disabled(! $this->canEdit) class="space-y-5">
                    <flux:input type="datetime-local" wire:model="startsAt" :label="__('Starts')" required />
                    <flux:input type="datetime-local" wire:model="endsAt" :label="__('Ends')" />
                    <flux:switch wire:model="allDay" :label="__('All-day event')" />
                    <flux:input wire:model="location" :label="__('Location')" />
                </fieldset>
                @if ($this->canEdit)
                    <livewire:admin.media-picker wire:model="featuredImageId" :label="__('Image')" />
                @endif
            </flux:card>
        </div>
    </div>
</div>
