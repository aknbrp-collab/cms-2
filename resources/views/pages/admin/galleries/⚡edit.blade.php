<?php

use App\Contracts\Reviewable;
use App\Enums\GalleryType;
use App\Livewire\ContentEditor;
use App\Models\Gallery;
use App\Models\GalleryItem;
use App\Models\MediaAsset;
use App\Models\Site;
use App\Support\Embed;
use App\Support\MediaUploader;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\WithFileUploads;

new #[Title('Edit gallery')] class extends ContentEditor {
    use WithFileUploads;

    public ?Gallery $gallery = null;

    /** The site this editor works on, fixed when it opens. */
    #[Locked]
    public ?string $siteId = null;

    public string $type = 'photo';

    public string $title = '';

    public string $slug = '';

    public string $description = '';

    public string $takenOn = '';

    public ?string $coverImageId = null;

    /** @var array<string, string> Item id => caption. */
    public array $captions = [];

    /** @var list<UploadedFile> */
    public array $photos = [];

    public ?string $pickedAssetId = null;

    public string $videoUrl = '';

    public string $videoCaption = '';

    public function mount(?Gallery $gallery = null): void
    {
        if ($gallery?->exists) {
            $this->authorize('view', $gallery);
            $this->gallery = $gallery;
            $this->siteId = $gallery->site_id;
            $this->fill([
                'type' => $gallery->type->value,
                'title' => $gallery->title,
                'slug' => $gallery->slug,
                'description' => (string) $gallery->description,
                'takenOn' => (string) $gallery->taken_on?->toDateString(),
                'coverImageId' => $gallery->cover_image_id,
                'captions' => $gallery->items()->pluck('caption', 'id')->map(fn ($caption) => (string) $caption)->all(),
            ]);
        } else {
            $this->authorize('create', Gallery::class);
            $this->siteId = current_site()?->id;
            $this->type = request()->query('type') === 'video' ? 'video' : 'photo';
        }
    }

    #[Computed]
    public function canEdit(): bool
    {
        return $this->gallery === null || Auth::user()->can('update', $this->gallery);
    }

    /**
     * @return Collection<int, GalleryItem>
     */
    #[Computed]
    public function items(): Collection
    {
        return $this->gallery?->items()->with('mediaAsset.media')->get() ?? new Collection;
    }

    public function save(): void
    {
        $this->gallery
            ? $this->authorize('update', $this->gallery)
            : $this->authorize('create', [Gallery::class, Site::query()->findOrFail($this->siteId)]);

        $this->slug = Str::slug($this->slug ?: $this->title);

        $this->validate([
            'type' => ['required', Rule::enum(GalleryType::class)],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'alpha_dash', 'max:255', Rule::unique('galleries')->where('site_id', $this->siteId)->ignore($this->gallery?->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'takenOn' => ['nullable', 'date'],
            'coverImageId' => ['nullable', 'exists:media_assets,id'],
            'captions.*' => ['nullable', 'string', 'max:255'],
        ]);

        $gallery = $this->gallery ?? new Gallery(['site_id' => $this->siteId, 'author_id' => Auth::id()]);
        $gallery->fill([
            'type' => $this->type,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description ?: null,
            'taken_on' => $this->takenOn ?: null,
            'cover_image_id' => $this->coverImageId,
        ])->save();

        foreach ($gallery->items as $item) {
            if (array_key_exists($item->id, $this->captions)) {
                $item->update(['caption' => $this->captions[$item->id] ?: null]);
            }
        }

        if ($this->gallery === null) {
            Flux::toast(variant: 'success', text: __('Draft created. Now add photos or videos.'));
            $this->redirectRoute('admin.galleries.edit', $gallery, navigate: true);

            return;
        }

        $this->gallery = $gallery->refresh();
        Flux::toast(variant: 'success', text: __('Saved.'));
    }

    public function updatedPhotos(MediaUploader $uploader): void
    {
        $this->authorizeItems();
        $this->authorize('media.create');

        $this->validate(['photos' => ['array', 'max:30'], 'photos.*' => ['image', 'max:12288']]);

        foreach ($this->photos as $photo) {
            $this->appendItem(['media_asset_id' => $uploader->store($photo, Auth::user())->id]);
        }

        $this->reset('photos');
        Flux::toast(variant: 'success', text: __('Photos added.'));
    }

    public function updatedPickedAssetId(): void
    {
        $this->authorizeItems();

        if ($asset = MediaAsset::query()->find($this->pickedAssetId)) {
            $this->appendItem(['media_asset_id' => $asset->id]);
        }

        $this->pickedAssetId = null;
    }

    public function addVideo(): void
    {
        $this->authorizeItems();

        $this->validate([
            'videoUrl' => ['required', 'url', function ($attribute, $value, $fail) {
                if (! Embed::isAllowed($value) || ! str_contains((string) Embed::toEmbedUrl($value), 'youtube')) {
                    $fail(__('Use a YouTube video link.'));
                }
            }],
            'videoCaption' => ['nullable', 'string', 'max:255'],
        ]);

        $this->appendItem(['video_url' => $this->videoUrl, 'caption' => $this->videoCaption ?: null]);
        $this->reset('videoUrl', 'videoCaption');
    }

    public function moveItem(string $id, int $direction): void
    {
        $this->authorizeItems();

        $items = $this->gallery->items()->get()->values();
        $index = $items->search(fn (GalleryItem $item) => $item->id === $id);

        if ($index === false || ! $items->has($index + $direction)) {
            return;
        }

        $ordered = $items->all();
        [$ordered[$index], $ordered[$index + $direction]] = [$ordered[$index + $direction], $ordered[$index]];

        foreach ($ordered as $position => $item) {
            $item->update(['sort_order' => $position]);
        }

        unset($this->items);
    }

    public function removeItem(string $id): void
    {
        $this->authorizeItems();

        $this->gallery->items()->whereKey($id)->delete();
        unset($this->captions[$id], $this->items);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function appendItem(array $attributes): void
    {
        $item = $this->gallery->items()->create([
            ...$attributes,
            'sort_order' => (int) $this->gallery->items()->max('sort_order') + 1,
        ]);

        $this->captions[$item->id] = (string) $item->caption;
        unset($this->items);
    }

    protected function authorizeItems(): void
    {
        abort_if($this->gallery === null, 404);
        $this->authorize('update', $this->gallery);
    }

    protected function workflowSubject(): (Reviewable&Model)|null
    {
        return $this->gallery;
    }

    protected function saveBeforeWorkflow(): bool
    {
        if ($this->canEdit) {
            $this->save();
        }

        return $this->gallery !== null;
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:link :href="route('admin.galleries.index')" wire:navigate class="text-sm">← {{ __('Galleries') }}</flux:link>
            <flux:heading size="xl" class="mt-1">{{ $gallery ? $gallery->title : __('New gallery') }}</flux:heading>
            <flux:text class="mt-1">{{ $gallery?->site->name ?? current_site()?->name }}</flux:text>
        </div>
        @if ($this->canEdit)
            <flux:button variant="primary" wire:click="save">{{ $gallery ? __('Save') : __('Save draft') }}</flux:button>
        @endif
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="min-w-0 space-y-6 lg:col-span-2">
            @if (! $this->canEdit)
                <flux:callout icon="lock-closed" color="zinc" :text="__('This gallery is locked while it is under review or published. An editor can unpublish it to allow changes.')" />
            @endif

            <flux:card class="space-y-5">
                <fieldset @disabled(! $this->canEdit) class="space-y-5">
                    <flux:input wire:model="title" :label="__('Title')" required />
                    <flux:input wire:model="slug" :label="__('Slug')" :description="__('Leave empty to generate from the title.')" />
                    <flux:textarea wire:model="description" :label="__('Description')" rows="3" />
                </fieldset>
            </flux:card>

            @if ($gallery)
                <flux:card class="space-y-5">
                    <flux:heading>{{ $type === 'video' ? __('Videos') : __('Photos') }}</flux:heading>

                    @if ($this->canEdit)
                        @if ($type === 'video')
                            <div class="grid gap-3 sm:grid-cols-[2fr_1fr_auto] sm:items-end">
                                <flux:input wire:model="videoUrl" :label="__('YouTube link')" placeholder="https://youtu.be/…" />
                                <flux:input wire:model="videoCaption" :label="__('Caption')" />
                                <flux:button wire:click="addVideo" icon="plus">{{ __('Add') }}</flux:button>
                            </div>
                        @else
                            <div class="flex flex-wrap items-end gap-4">
                                <flux:input type="file" wire:model="photos" multiple accept="image/*" :label="__('Upload photos')" class="max-w-xs" />
                                <livewire:admin.media-picker wire:model.live="pickedAssetId" :label="__('Or add from library')" />
                            </div>
                            <div wire:loading wire:target="photos"><flux:text size="sm">{{ __('Uploading…') }}</flux:text></div>
                            <flux:error name="photos" />
                            <flux:error name="photos.*" />
                        @endif
                    @endif

                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        @forelse ($this->items as $item)
                            <div wire:key="item-{{ $item->id }}" class="overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700">
                                @if ($item->mediaAsset)
                                    <img src="{{ $item->mediaAsset->url('thumb') }}" alt="" class="aspect-[4/3] w-full object-cover">
                                @elseif ($thumb = $item->videoThumbnail())
                                    <img src="{{ $thumb }}" alt="" class="aspect-[4/3] w-full object-cover">
                                @endif
                                <div class="space-y-2 p-2">
                                    <flux:input size="sm" wire:model="captions.{{ $item->id }}" :placeholder="__('Caption')" :disabled="! $this->canEdit" />
                                    @if ($this->canEdit)
                                        <div class="flex justify-between">
                                            <div class="flex">
                                                <flux:button size="xs" variant="ghost" icon="arrow-left" wire:click="moveItem('{{ $item->id }}', -1)" :disabled="$loop->first" />
                                                <flux:button size="xs" variant="ghost" icon="arrow-right" wire:click="moveItem('{{ $item->id }}', 1)" :disabled="$loop->last" />
                                            </div>
                                            <flux:button size="xs" variant="ghost" icon="trash" wire:click="removeItem('{{ $item->id }}')" wire:confirm="{{ __('Remove from this gallery? The file stays in the media library.') }}" />
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <flux:text class="col-span-full">{{ __('Nothing here yet.') }}</flux:text>
                        @endforelse
                    </div>
                </flux:card>
            @endif
        </div>

        <div class="space-y-6">
            @if ($gallery)
                <x-admin.workflow-panel :content="$gallery" />
            @else
                <flux:callout icon="information-circle" :text="__('Save the draft first. Then you can add photos or videos.')" />
            @endif

            <flux:card class="space-y-5">
                <fieldset @disabled(! $this->canEdit) class="space-y-5">
                    <flux:radio.group wire:model.live="type" :label="__('Type')" variant="segmented" :disabled="$gallery !== null">
                        @foreach (GalleryType::cases() as $case)
                            <flux:radio :value="$case->value" :label="$case->label()" />
                        @endforeach
                    </flux:radio.group>
                    <flux:input type="date" wire:model="takenOn" :label="__('Date')" />
                </fieldset>
                @if ($this->canEdit && $type === 'photo')
                    <livewire:admin.media-picker wire:model="coverImageId" :label="__('Cover image')" />
                @endif
            </flux:card>
        </div>
    </div>
</div>
