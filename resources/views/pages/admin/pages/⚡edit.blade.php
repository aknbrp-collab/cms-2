<?php

use App\Contracts\Reviewable;
use App\Enums\BlockType;
use App\Enums\PageTemplate;
use App\Livewire\ContentEditor;
use App\Models\Page;
use App\Models\Site;
use App\Support\Embed;
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

new #[Title('Edit page')] class extends ContentEditor {
    public ?Page $page = null;

    /** The site this editor works on, fixed when it opens. */
    #[Locked]
    public ?string $siteId = null;

    public string $title = '';

    public string $slug = '';

    public ?string $parentId = null;

    public string $template = 'default';

    public int $sortOrder = 0;

    /** @var list<array{id: string, type: string, data: array<string, mixed>}> */
    public array $blocks = [];

    /** @var array{meta_title: ?string, meta_description: ?string, og_image_id: ?string, canonical_url: ?string, noindex: bool} */
    public array $seo = ['meta_title' => null, 'meta_description' => null, 'og_image_id' => null, 'canonical_url' => null, 'noindex' => false];

    public function mount(?Page $page = null): void
    {
        if ($page?->exists) {
            $this->authorize('view', $page);
            $this->page = $page;
            $this->siteId = $page->site_id;
            $this->fill([
                'title' => $page->title,
                'slug' => $page->slug,
                'parentId' => $page->parent_id,
                'template' => $page->template->value,
                'sortOrder' => $page->sort_order,
                'blocks' => $page->blocks ?? [],
            ]);

            if ($page->seo) {
                $this->seo = array_merge($this->seo, $page->seo->only(array_keys($this->seo)));
            }
        } else {
            $this->authorize('create', Page::class);
            $this->siteId = current_site()?->id;
            $this->addBlock(BlockType::RichText->value);
        }
    }

    #[Computed]
    public function canEdit(): bool
    {
        return $this->page === null || Auth::user()->can('update', $this->page);
    }

    /**
     * Pages that can be chosen as parent.
     *
     * @return Collection<int, Page>
     */
    #[Computed]
    public function parents(): Collection
    {
        $siteId = $this->siteId;

        return Page::query()
            ->forSite((string) $siteId)
            ->whereNull('parent_id')
            ->when($this->page, fn ($query, Page $page) => $query->whereKeyNot($page->id))
            ->orderBy('title')
            ->get();
    }

    public function addBlock(string $type): void
    {
        $blockType = BlockType::from($type);

        $this->blocks[] = ['id' => (string) Str::uuid(), 'type' => $blockType->value, 'data' => $blockType->defaults()];
    }

    public function removeBlock(int $index): void
    {
        unset($this->blocks[$index]);
        $this->blocks = array_values($this->blocks);
    }

    public function moveBlock(int $index, int $direction): void
    {
        $target = $index + $direction;

        if (! isset($this->blocks[$index], $this->blocks[$target])) {
            return;
        }

        [$this->blocks[$index], $this->blocks[$target]] = [$this->blocks[$target], $this->blocks[$index]];
    }

    public function save(): void
    {
        $this->page
            ? $this->authorize('update', $this->page)
            : $this->authorize('create', [Page::class, Site::query()->findOrFail($this->siteId)]);

        $siteId = $this->siteId;
        $this->slug = Str::slug($this->slug ?: $this->title);

        $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'alpha_dash', 'max:255', Rule::unique('pages')->where('site_id', $siteId)->ignore($this->page?->id)],
            'parentId' => ['nullable', Rule::exists('pages', 'id')->where('site_id', $siteId)->whereNull('parent_id')],
            'template' => ['required', Rule::enum(PageTemplate::class)],
            'sortOrder' => ['integer', 'min:0', 'max:9999'],
            'blocks' => ['array', 'max:50'],
            'blocks.*.type' => ['required', Rule::enum(BlockType::class)],
            'blocks.*.data.html' => ['nullable', 'string', 'max:500000'],
            'blocks.*.data.media_asset_id' => ['nullable', 'exists:media_assets,id'],
            'blocks.*.data.caption' => ['nullable', 'string', 'max:500'],
            'blocks.*.data.title' => ['nullable', 'string', 'max:255'],
            'blocks.*.data.text' => ['nullable', 'string', 'max:2000'],
            'blocks.*.data.button_label' => ['nullable', 'string', 'max:100'],
            'blocks.*.data.button_url' => ['nullable', 'string', 'max:2048'],
            'blocks.*.data.url' => ['nullable', 'url', function ($attribute, $value, $fail) {
                if (filled($value) && ! Embed::isAllowed($value)) {
                    $fail(__('Only YouTube videos and Google Maps embeds are supported.'));
                }
            }],
            'seo.meta_title' => ['nullable', 'string', 'max:255'],
            'seo.meta_description' => ['nullable', 'string', 'max:500'],
            'seo.og_image_id' => ['nullable', 'exists:media_assets,id'],
            'seo.canonical_url' => ['nullable', 'url', 'max:2048'],
            'seo.noindex' => ['boolean'],
        ]);

        $page = $this->page ?? new Page(['site_id' => $siteId, 'author_id' => Auth::id()]);
        $page->fill([
            'title' => $this->title,
            'slug' => $this->slug,
            'parent_id' => $this->parentId ?: null,
            'template' => $this->template,
            'sort_order' => $this->sortOrder,
            'blocks' => $this->cleanBlocks(),
        ])->save();
        $page->saveSeo($this->seo);

        if ($this->page === null) {
            Flux::toast(variant: 'success', text: __('Draft created.'));
            $this->redirectRoute('admin.pages.edit', $page, navigate: true);

            return;
        }

        $this->page = $page->refresh();
        Flux::toast(variant: 'success', text: __('Saved.'));
    }

    /**
     * Keep only known fields per block type and sanitise rich text.
     *
     * @return list<array{id: string, type: string, data: array<string, mixed>}>
     */
    protected function cleanBlocks(): array
    {
        return array_map(function (array $block) {
            $type = BlockType::from($block['type']);
            $data = array_intersect_key($block['data'] ?? [], $type->defaults()) + $type->defaults();

            if ($type === BlockType::RichText) {
                $data['html'] = RichText::clean($data['html']);
            }

            return ['id' => $block['id'], 'type' => $type->value, 'data' => $data];
        }, $this->blocks);
    }

    protected function workflowSubject(): (Reviewable&Model)|null
    {
        return $this->page;
    }

    protected function saveBeforeWorkflow(): bool
    {
        if ($this->canEdit) {
            $this->save();
        }

        return $this->page !== null;
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:link :href="route('admin.pages.index')" wire:navigate class="text-sm">← {{ __('Pages') }}</flux:link>
            <flux:heading size="xl" class="mt-1">{{ $page ? $page->title : __('New page') }}</flux:heading>
            <flux:text class="mt-1">{{ $page?->site->name ?? current_site()?->name }}</flux:text>
        </div>
        @if ($this->canEdit)
            <flux:button variant="primary" wire:click="save">{{ $page ? __('Save') : __('Save draft') }}</flux:button>
        @endif
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="min-w-0 space-y-6 lg:col-span-2">
            @if (! $this->canEdit)
                <flux:callout icon="lock-closed" color="zinc" :text="__('This page is locked while it is under review or published. An editor can unpublish it to allow changes.')" />
            @endif

            <flux:card class="space-y-5">
                <flux:input wire:model="title" :label="__('Title')" required :disabled="! $this->canEdit" />
                <flux:input wire:model="slug" :label="__('Slug')" :description="__('The page is served at /slug.')" :disabled="! $this->canEdit" />
            </flux:card>

            @if ($this->canEdit)
                <div class="space-y-4">
                    @foreach ($blocks as $index => $block)
                        @php $type = BlockType::from($block['type']); @endphp
                        <flux:card wire:key="block-{{ $block['id'] }}" class="space-y-4">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <flux:icon :name="$type->icon()" class="size-5 text-zinc-400" />
                                    <flux:heading>{{ $type->label() }}</flux:heading>
                                </div>
                                <div class="flex gap-1">
                                    <flux:button size="xs" variant="ghost" icon="arrow-up" wire:click="moveBlock({{ $index }}, -1)" :disabled="$loop->first" />
                                    <flux:button size="xs" variant="ghost" icon="arrow-down" wire:click="moveBlock({{ $index }}, 1)" :disabled="$loop->last" />
                                    <flux:button size="xs" variant="ghost" icon="trash" wire:click="removeBlock({{ $index }})" wire:confirm="{{ __('Remove this block?') }}" />
                                </div>
                            </div>

                            @switch($type)
                                @case(BlockType::RichText)
                                    {{-- Keyed by index too: the editor re-mounts on its new path after a move. --}}
                                    <div wire:key="rt-{{ $block['id'] }}-{{ $index }}">
                                        <x-admin.rich-text model="blocks.{{ $index }}.data.html" />
                                    </div>
                                    @break
                                @case(BlockType::Image)
                                    <livewire:admin.media-picker wire:model="blocks.{{ $index }}.data.media_asset_id" :key="'img-'.$block['id'].'-'.$index" />
                                    <flux:input wire:model="blocks.{{ $index }}.data.caption" :label="__('Caption')" />
                                    @break
                                @case(BlockType::CallToAction)
                                    <flux:input wire:model="blocks.{{ $index }}.data.title" :label="__('Heading')" />
                                    <flux:textarea wire:model="blocks.{{ $index }}.data.text" :label="__('Text')" rows="2" />
                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <flux:input wire:model="blocks.{{ $index }}.data.button_label" :label="__('Button label')" />
                                        <flux:input wire:model="blocks.{{ $index }}.data.button_url" :label="__('Button link')" />
                                    </div>
                                    @break
                                @case(BlockType::Embed)
                                    <flux:input wire:model="blocks.{{ $index }}.data.url" :label="__('YouTube or Google Maps embed link')" />
                                    <flux:input wire:model="blocks.{{ $index }}.data.caption" :label="__('Caption')" />
                                    @break
                            @endswitch
                        </flux:card>
                    @endforeach

                    <div class="flex flex-wrap items-center gap-2">
                        <flux:text size="sm">{{ __('Add block:') }}</flux:text>
                        @foreach (BlockType::cases() as $case)
                            <flux:button size="sm" :icon="$case->icon()" wire:click="addBlock('{{ $case->value }}')">{{ $case->label() }}</flux:button>
                        @endforeach
                    </div>
                </div>

                <x-admin.seo-fields model="seo" />
            @endif
        </div>

        <div class="space-y-6">
            @if ($page)
                <x-admin.workflow-panel :content="$page" />
            @else
                <flux:callout icon="information-circle" :text="__('Save the draft first. Then you can submit it for review or publish it.')" />
            @endif

            <flux:card class="space-y-5">
                <flux:select wire:model="parentId" :label="__('Parent page')" :disabled="! $this->canEdit">
                    <option value="">{{ __('None (top level)') }}</option>
                    @foreach ($this->parents as $parent)
                        <option value="{{ $parent->id }}">{{ $parent->title }}</option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="template" :label="__('Template')" :disabled="! $this->canEdit">
                    @foreach (PageTemplate::cases() as $case)
                        <option value="{{ $case->value }}">{{ $case->label() }}</option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="sortOrder" type="number" min="0" :label="__('Order')" :disabled="! $this->canEdit" />
            </flux:card>
        </div>
    </div>
</div>
