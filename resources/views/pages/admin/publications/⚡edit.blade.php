<?php

use App\Contracts\Reviewable;
use App\Enums\PublicationType;
use App\Livewire\ContentEditor;
use App\Models\Journal;
use App\Models\Publication;
use App\Models\Site;
use App\Support\RichText;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;

new #[Title('Edit publication')] class extends ContentEditor {
    public ?Publication $publication = null;

    /** The site this editor works on, fixed when it opens. */
    #[Locked]
    public ?string $siteId = null;

    public string $type = 'research';

    public string $title = '';

    public string $slug = '';

    public string $authors = '';

    public int $year = 0;

    public string $abstract = '';

    public string $body = '';

    public ?string $journalId = null;

    public string $volume = '';

    public string $issue = '';

    public string $pages = '';

    public string $doi = '';

    public string $keywords = '';

    public ?string $fileId = null;

    public string $externalUrl = '';

    /** @var array{meta_title: ?string, meta_description: ?string, og_image_id: ?string, canonical_url: ?string, noindex: bool} */
    public array $seo = ['meta_title' => null, 'meta_description' => null, 'og_image_id' => null, 'canonical_url' => null, 'noindex' => false];

    public function mount(?Publication $publication = null): void
    {
        if ($publication?->exists) {
            $this->authorize('view', $publication);
            $this->publication = $publication;
            $this->siteId = $publication->site_id;
            $this->fill([
                'type' => $publication->type->value,
                'title' => $publication->title,
                'slug' => $publication->slug,
                'authors' => $publication->authors,
                'year' => $publication->year,
                'abstract' => (string) $publication->abstract,
                'body' => (string) $publication->body,
                'journalId' => $publication->journal_id,
                'volume' => (string) $publication->volume,
                'issue' => (string) $publication->issue,
                'pages' => (string) $publication->pages,
                'doi' => (string) $publication->doi,
                'keywords' => (string) $publication->keywords,
                'fileId' => $publication->file_id,
                'externalUrl' => (string) $publication->external_url,
            ]);

            if ($publication->seo) {
                $this->seo = array_merge($this->seo, $publication->seo->only(array_keys($this->seo)));
            }
        } else {
            $this->authorize('create', Publication::class);
            $this->siteId = $this->siteIdForNewContent();
            $this->year = (int) now()->format('Y');
        }
    }

    #[Computed]
    public function canEdit(): bool
    {
        return $this->publication === null || Auth::user()->can('update', $this->publication);
    }

    /**
     * @return Collection<int, Journal>
     */
    #[Computed]
    public function journals(): Collection
    {
        return Journal::query()->forSite((string) $this->siteId)->orderBy('name')->get(['id', 'name']);
    }

    public function save(): void
    {
        $this->publication
            ? $this->authorize('update', $this->publication)
            : $this->authorize('create', [Publication::class, Site::query()->findOrFail($this->siteId)]);

        $this->slug = Str::slug($this->slug ?: $this->title);

        $this->validate([
            'type' => ['required', Rule::enum(PublicationType::class)],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'alpha_dash', 'max:255', Rule::unique('publications')->where('site_id', $this->siteId)->ignore($this->publication?->id)],
            'authors' => ['required', 'string', 'max:255'],
            'year' => ['required', 'integer', 'min:1900', 'max:'.(now()->year + 1)],
            'abstract' => ['nullable', 'string', 'max:10000'],
            'body' => ['nullable', 'string', 'max:500000'],
            'journalId' => ['nullable', Rule::exists('journals', 'id')->where('site_id', $this->siteId)],
            'volume' => ['nullable', 'string', 'max:20'],
            'issue' => ['nullable', 'string', 'max:20'],
            'pages' => ['nullable', 'string', 'max:20'],
            'doi' => ['nullable', 'string', 'max:255', 'regex:/^10\.\d{4,9}\/\S+$/'],
            'keywords' => ['nullable', 'string', 'max:255'],
            'fileId' => ['nullable', 'exists:media_assets,id'],
            'externalUrl' => ['nullable', 'url', 'max:2048'],
            'seo.meta_title' => ['nullable', 'string', 'max:255'],
            'seo.meta_description' => ['nullable', 'string', 'max:500'],
            'seo.og_image_id' => ['nullable', 'exists:media_assets,id'],
            'seo.canonical_url' => ['nullable', 'url', 'max:2048'],
            'seo.noindex' => ['boolean'],
        ], ['doi.regex' => __('Enter the DOI without https://doi.org/, e.g. 10.1234/abc.2024.1')]);

        $publication = $this->publication ?? new Publication(['site_id' => $this->siteId, 'author_id' => Auth::id()]);
        $publication->fill([
            'type' => $this->type,
            'title' => $this->title,
            'slug' => $this->slug,
            'authors' => $this->authors,
            'year' => $this->year,
            'abstract' => $this->abstract ?: null,
            'body' => RichText::clean($this->body) ?: null,
            'journal_id' => $this->journalId ?: null,
            'volume' => $this->volume ?: null,
            'issue' => $this->issue ?: null,
            'pages' => $this->pages ?: null,
            'doi' => $this->doi ?: null,
            'keywords' => $this->keywords ?: null,
            'file_id' => $this->fileId,
            'external_url' => $this->externalUrl ?: null,
        ])->save();
        $publication->saveSeo($this->seo);

        if ($this->publication === null) {
            Flux::toast(variant: 'success', text: __('Draft created.'));
            $this->redirectRoute('admin.publications.edit', $publication, navigate: true);

            return;
        }

        $this->publication = $publication->refresh();
        Flux::toast(variant: 'success', text: __('Saved.'));
    }

    protected function workflowSubject(): (Reviewable&Model)|null
    {
        return $this->publication;
    }

    protected function saveBeforeWorkflow(): bool
    {
        if ($this->canEdit) {
            $this->save();
        }

        return $this->publication !== null;
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:link :href="route('admin.publications.index')" wire:navigate class="text-sm">← {{ __('Publications') }}</flux:link>
            <flux:heading size="xl" class="mt-1">{{ $publication ? $publication->title : __('New publication') }}</flux:heading>
            <flux:text class="mt-1">{{ $publication?->site->name ?? current_site()?->name }}</flux:text>
        </div>
        @if ($this->canEdit)
            <flux:button variant="primary" wire:click="save">{{ $publication ? __('Save') : __('Save draft') }}</flux:button>
        @endif
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="min-w-0 space-y-6 lg:col-span-2">
            @if (! $this->canEdit)
                <flux:callout icon="lock-closed" color="zinc" :text="__('This publication is locked while it is under review or published. An editor can unpublish it to allow changes.')" />
            @endif

            <fieldset @disabled(! $this->canEdit) class="space-y-6">
                <flux:card class="space-y-5">
                    <flux:input wire:model="title" :label="__('Title')" required />
                    <flux:input wire:model="authors" :label="__('Authors')" :description="__('Separate several authors with commas.')" required />
                    <flux:textarea wire:model="abstract" :label="__('Abstract')" rows="5" />
                    @if ($this->canEdit)
                        <x-admin.rich-text model="body" :label="__('Full text (optional)')" />
                    @endif
                    <flux:input wire:model="keywords" :label="__('Keywords')" :description="__('Comma separated.')" />
                </flux:card>

                <flux:card class="space-y-5">
                    <flux:heading>{{ __('Journal & identifiers') }}</flux:heading>
                    <flux:select wire:model="journalId" :label="__('Journal')">
                        <option value="">{{ __('Not in a journal') }}</option>
                        @foreach ($this->journals as $journal)
                            <option value="{{ $journal->id }}">{{ $journal->name }}</option>
                        @endforeach
                    </flux:select>
                    <div class="grid gap-5 sm:grid-cols-3">
                        <flux:input wire:model="volume" :label="__('Volume')" />
                        <flux:input wire:model="issue" :label="__('Issue')" />
                        <flux:input wire:model="pages" :label="__('Pages')" placeholder="1-20" />
                    </div>
                    <div class="grid gap-5 sm:grid-cols-2">
                        <flux:input wire:model="doi" label="DOI" placeholder="10.1234/abc.2024.1" />
                        <flux:input wire:model="externalUrl" type="url" :label="__('External link (OJS, repository)')" />
                    </div>
                </flux:card>

                @if ($this->canEdit)
                    <x-admin.seo-fields model="seo" />
                @endif
            </fieldset>
        </div>

        <div class="space-y-6">
            @if ($publication)
                <x-admin.workflow-panel :content="$publication" />
            @else
                <flux:callout icon="information-circle" :text="__('Save the draft first. Then you can submit it for review or publish it.')" />
            @endif

            <flux:card class="space-y-5">
                <fieldset @disabled(! $this->canEdit) class="space-y-5">
                    <flux:select wire:model="type" :label="__('Type')">
                        @foreach (PublicationType::cases() as $case)
                            <option value="{{ $case->value }}">{{ $case->label() }}</option>
                        @endforeach
                    </flux:select>
                    <flux:input wire:model="year" type="number" :label="__('Year')" required />
                    <flux:input wire:model="slug" :label="__('Slug')" :description="__('Leave empty to generate from the title.')" />
                </fieldset>
                @if ($this->canEdit)
                    <livewire:admin.media-picker wire:model="fileId" kind="document" :label="__('Document (PDF)')" />
                @endif
            </flux:card>
        </div>
    </div>
</div>
