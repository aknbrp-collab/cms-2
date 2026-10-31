<?php

use App\Contracts\Reviewable;
use App\Enums\PostType;
use App\Livewire\ContentEditor;
use App\Models\Category;
use App\Models\Post;
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

new #[Title('Edit post')] class extends ContentEditor {
    public ?Post $post = null;

    /** The site this editor works on, fixed when it opens. */
    #[Locked]
    public ?string $siteId = null;

    public string $type = 'news';

    public ?string $categoryId = null;

    public string $title = '';

    public string $slug = '';

    public string $excerpt = '';

    public string $body = '';

    public ?string $featuredImageId = null;

    public bool $isFeatured = false;

    /** @var array{meta_title: ?string, meta_description: ?string, og_image_id: ?string, canonical_url: ?string, noindex: bool} */
    public array $seo = ['meta_title' => null, 'meta_description' => null, 'og_image_id' => null, 'canonical_url' => null, 'noindex' => false];

    public function mount(?Post $post = null): void
    {
        if ($post?->exists) {
            $this->authorize('view', $post);
            $this->post = $post;
            $this->siteId = $post->site_id;
            $this->fill([
                'type' => $post->type->value,
                'categoryId' => $post->category_id,
                'title' => $post->title,
                'slug' => $post->slug,
                'excerpt' => (string) $post->excerpt,
                'body' => (string) $post->body,
                'featuredImageId' => $post->featured_image_id,
                'isFeatured' => $post->is_featured,
            ]);

            if ($post->seo) {
                $this->seo = array_merge($this->seo, $post->seo->only(array_keys($this->seo)));
            }
        } else {
            $this->authorize('create', Post::class);
            $this->siteId = current_site()?->id;
        }
    }

    #[Computed]
    public function canEdit(): bool
    {
        return $this->post === null || Auth::user()->can('update', $this->post);
    }

    /**
     * @return Collection<int, Category>
     */
    #[Computed]
    public function categories(): Collection
    {
        $siteId = $this->siteId;

        return Category::query()->forSite((string) $siteId)->orderBy('name')->get();
    }

    public function save(): void
    {
        $this->post
            ? $this->authorize('update', $this->post)
            : $this->authorize('create', [Post::class, Site::query()->findOrFail($this->siteId)]);

        $siteId = $this->siteId;
        $this->slug = Str::slug($this->slug ?: $this->title);

        $this->validate([
            'type' => ['required', Rule::enum(PostType::class)],
            'categoryId' => ['nullable', Rule::exists('categories', 'id')->where('site_id', $siteId)],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'alpha_dash', 'max:255', Rule::unique('posts')->where('site_id', $siteId)->ignore($this->post?->id)],
            'excerpt' => ['nullable', 'string', 'max:1000'],
            'body' => ['nullable', 'string', 'max:500000'],
            'featuredImageId' => ['nullable', 'exists:media_assets,id'],
            'isFeatured' => ['boolean'],
            'seo.meta_title' => ['nullable', 'string', 'max:255'],
            'seo.meta_description' => ['nullable', 'string', 'max:500'],
            'seo.og_image_id' => ['nullable', 'exists:media_assets,id'],
            'seo.canonical_url' => ['nullable', 'url', 'max:2048'],
            'seo.noindex' => ['boolean'],
        ]);

        $post = $this->post ?? new Post(['site_id' => $siteId, 'author_id' => Auth::id()]);
        $post->fill([
            'type' => $this->type,
            'category_id' => $this->categoryId ?: null,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt ?: null,
            'body' => RichText::clean($this->body),
            'featured_image_id' => $this->featuredImageId,
            'is_featured' => $this->isFeatured,
        ])->save();
        $post->saveSeo($this->seo);

        if ($this->post === null) {
            Flux::toast(variant: 'success', text: __('Draft created.'));
            $this->redirectRoute('admin.posts.edit', $post, navigate: true);

            return;
        }

        $this->post = $post->refresh();
        Flux::toast(variant: 'success', text: __('Saved.'));
    }

    protected function workflowSubject(): (Reviewable&Model)|null
    {
        return $this->post;
    }

    protected function saveBeforeWorkflow(): bool
    {
        if ($this->canEdit) {
            $this->save();
        }

        return $this->post !== null;
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:link :href="route('admin.posts.index')" wire:navigate class="text-sm">← {{ __('Posts') }}</flux:link>
            <flux:heading size="xl" class="mt-1">{{ $post ? $post->title : __('New post') }}</flux:heading>
            <flux:text class="mt-1">{{ $post?->site->name ?? current_site()?->name }}</flux:text>
        </div>
        @if ($this->canEdit)
            <flux:button variant="primary" wire:click="save">{{ $post ? __('Save') : __('Save draft') }}</flux:button>
        @endif
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="min-w-0 space-y-6 lg:col-span-2">
            <flux:card class="space-y-5">
                @if ($this->canEdit)
                    <flux:input wire:model="title" :label="__('Title')" required />
                    <flux:input wire:model="slug" :label="__('Slug')" :description="__('Part of the URL. Leave empty to generate from the title.')" />
                    <flux:textarea wire:model="excerpt" :label="__('Excerpt')" rows="2" :description="__('Short summary for listings and search results.')" />
                    <x-admin.rich-text model="body" :label="__('Content')" />
                @else
                    <flux:callout icon="lock-closed" color="zinc" :text="__('This post is locked while it is under review or published. An editor can unpublish it to allow changes.')" />
                    <flux:heading size="lg">{{ $title }}</flux:heading>
                    <div class="trix-content prose max-w-none">{!! $body !!}</div>
                @endif
            </flux:card>

            @if ($this->canEdit)
                <x-admin.seo-fields model="seo" />
            @endif
        </div>

        <div class="space-y-6">
            @if ($post)
                <x-admin.workflow-panel :content="$post" />
            @else
                <flux:callout icon="information-circle" :text="__('Save the draft first. Then you can submit it for review or publish it.')" />
            @endif

            <flux:card class="space-y-5">
                <flux:select wire:model="type" :label="__('Type')" :disabled="! $this->canEdit">
                    @foreach (PostType::cases() as $case)
                        <option value="{{ $case->value }}">{{ $case->label() }}</option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="categoryId" :label="__('Category')" :disabled="! $this->canEdit">
                    <option value="">{{ __('No category') }}</option>
                    @foreach ($this->categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </flux:select>
                @if ($this->canEdit)
                    <livewire:admin.media-picker wire:model="featuredImageId" :label="__('Featured image')" />
                    <flux:switch wire:model="isFeatured" :label="__('Feature on the homepage')" />
                @endif
            </flux:card>
        </div>
    </div>
</div>
