<?php

use App\Models\HeroSlide;
use App\Models\Site;
use App\Support\Visibility;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Homepage slider')] class extends Component {
    public ?string $editingId = null;

    public ?string $imageId = null;

    public string $label = '';

    public string $slideTitle = '';

    public string $text = '';

    public string $buttonLabel = '';

    public string $buttonUrl = '';

    public string $startsAt = '';

    public string $endsAt = '';

    /** Bumped on every form reset so the media picker re-mounts. */
    public int $formVersion = 0;

    public function mount(): void
    {
        $this->authorize('hero_slides.view');
    }

    /**
     * The current site when it uses the slider, otherwise null.
     */
    #[Computed]
    public function site(): ?Site
    {
        $site = current_site();

        return $site?->hasModule('hero_slides') && Auth::user()->canAccessSite($site) ? $site : null;
    }

    /**
     * Pondok shows slides as photo cards with a label and title (home_hero);
     * Ma'had shows them behind the hero text with a button.
     */
    #[Computed]
    public function cards(): bool
    {
        return (bool) $this->site?->hasModule('home_hero');
    }

    /**
     * @return Collection<int, HeroSlide>
     */
    #[Computed]
    public function slides(): Collection
    {
        return $this->site
            ? HeroSlide::query()->with('image.media')->orderBy('sort_order')->get()
            : new Collection;
    }

    public function create(): void
    {
        $this->authorizeSlide('create');
        $this->resetForm();
        Flux::modal('slide-form')->show();
    }

    public function edit(string $id): void
    {
        $slide = $this->findSlide($id);
        $this->authorizeSlide('update', $slide);

        $this->resetForm();
        $this->fill([
            'editingId' => $slide->id,
            'imageId' => $slide->image_id,
            'label' => (string) $slide->label,
            'slideTitle' => (string) $slide->title,
            'text' => (string) $slide->text,
            'buttonLabel' => (string) $slide->button_label,
            'buttonUrl' => (string) $slide->button_url,
            'startsAt' => (string) $slide->starts_at?->format('Y-m-d\TH:i'),
            'endsAt' => (string) $slide->ends_at?->format('Y-m-d\TH:i'),
        ]);

        Flux::modal('slide-form')->show();
    }

    public function save(): void
    {
        $slide = $this->editingId ? $this->findSlide($this->editingId) : null;
        $site = $this->authorizeSlide($slide ? 'update' : 'create', $slide);

        $this->validate([
            'imageId' => ['required', 'exists:media_assets,id'],
            'label' => ['nullable', 'string', 'max:60'],
            'slideTitle' => ['nullable', 'string', 'max:120'],
            'text' => ['nullable', 'string', 'max:300'],
            'buttonLabel' => ['nullable', $this->cards ? 'prohibited' : 'required_with:buttonUrl', 'string', 'max:40'],
            'buttonUrl' => ['nullable', 'required_with:buttonLabel', 'string', 'max:2048', 'regex:#^(https?://|/)#'],
            'startsAt' => ['nullable', 'date'],
            'endsAt' => ['nullable', 'date', 'after:startsAt'],
        ], [
            'buttonUrl.regex' => __('Use a full address (https://…) or a path on this site (/…).'),
        ], [
            'imageId' => __('image'),
            'slideTitle' => __('title'),
            'buttonLabel' => __('button text'),
            'buttonUrl' => __('button link'),
            'startsAt' => __('show from'),
            'endsAt' => __('show until'),
        ]);

        $slide ??= new HeroSlide([
            'site_id' => $site->id,
            'sort_order' => (int) HeroSlide::query()->max('sort_order') + 1,
        ]);

        $slide->fill([
            'image_id' => $this->imageId,
            'label' => $this->label ?: null,
            'title' => $this->slideTitle ?: null,
            'text' => $this->text ?: null,
            'button_label' => $this->buttonLabel ?: null,
            'button_url' => $this->buttonUrl ?: null,
            'starts_at' => $this->startsAt ? CarbonImmutable::parse($this->startsAt) : null,
            'ends_at' => $this->endsAt ? CarbonImmutable::parse($this->endsAt) : null,
        ])->save();

        unset($this->slides);
        Flux::modal('slide-form')->close();
        Flux::toast(variant: 'success', text: __('Slide saved.'));
        $this->resetForm();
    }

    public function move(string $id, int $direction): void
    {
        $this->authorizeSlide('update', $this->findSlide($id));

        $slides = $this->slides->values();
        $index = $slides->search(fn (HeroSlide $slide) => $slide->id === $id);
        $target = $slides->get($index + $direction);

        if ($index === false || $target === null) {
            return;
        }

        $ordered = $slides->all();
        [$ordered[$index], $ordered[$index + $direction]] = [$ordered[$index + $direction], $ordered[$index]];

        foreach ($ordered as $position => $slide) {
            $slide->update(['sort_order' => $position]);
        }

        unset($this->slides);
    }

    public function toggleHidden(string $id): void
    {
        $slide = $this->findSlide($id);
        $this->authorizeSlide('update', $slide);

        $hidden = Visibility::toggle($slide);
        unset($this->slides);

        Flux::toast(variant: 'success', text: $hidden ? __('Hidden from the website.') : __('Shown on the website again.'));
    }

    public function delete(string $id): void
    {
        $slide = $this->findSlide($id);
        $this->authorizeSlide('delete', $slide);
        $slide->delete();

        unset($this->slides);
        Flux::toast(text: __('Slide deleted.'));
    }

    protected function findSlide(string $id): HeroSlide
    {
        return HeroSlide::query()->with('site')->findOrFail($id);
    }

    /**
     * The slide's site (or the current site for new slides) must use the slider,
     * be one the user manages, and the user needs the matching permission.
     */
    protected function authorizeSlide(string $action, ?HeroSlide $slide = null): Site
    {
        $site = $slide?->site ?? $this->site;
        $user = Auth::user();

        if (! $site instanceof Site || ! $site->hasModule('hero_slides') || ! $user->canAccessSite($site) || ! $user->can("hero_slides.{$action}")) {
            throw new AuthorizationException(__('You are not allowed to do this.'));
        }

        return $site;
    }

    protected function resetForm(): void
    {
        $this->reset('editingId', 'imageId', 'label', 'slideTitle', 'text', 'buttonLabel', 'buttonUrl', 'startsAt', 'endsAt');
        $this->resetValidation();
        $this->formVersion++;
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Homepage slider') }}</flux:heading>
            <flux:text class="mt-1">{{ $this->cards ? __('Photo cards in the hero at the top of the home page. They change every few seconds.') : __('Pictures behind the hero at the top of the home page. They change every few seconds; the site name and calligraphy stay on top.') }}</flux:text>
        </div>
        @if ($this->site)
            @can('hero_slides.create')
                <flux:button variant="primary" icon="plus" wire:click="create">{{ __('New slide') }}</flux:button>
            @endcan
        @endif
    </div>

    @if (! current_site())
        <x-admin.needs-site />
    @elseif (! $this->site)
        <flux:callout icon="information-circle" color="zinc" :heading="__('Not used on this site')" :text="__('This site does not use the homepage slider, or you do not manage it.')" />
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @forelse ($this->slides as $slide)
                @php $state = $slide->scheduleState(); @endphp
                <flux:card wire:key="slide-{{ $slide->id }}" @class(['flex flex-col overflow-hidden p-0!', 'opacity-60' => $slide->is_hidden])>
                    <div class="relative aspect-[16/7] bg-zinc-100 dark:bg-zinc-800">
                        @if ($slide->image)
                            <img src="{{ $slide->image->url('thumb') }}" alt="" class="h-full w-full object-cover">
                        @else
                            <div class="flex h-full items-center justify-center text-sm text-zinc-500">{{ __('Image missing: this slide is skipped') }}</div>
                        @endif
                        <span class="absolute start-2 top-2 rounded-full bg-black/60 px-2 py-0.5 text-xs font-semibold text-white">{{ $loop->iteration }}</span>
                    </div>
                    <div class="flex flex-1 flex-col gap-2 p-4">
                        <div class="flex flex-wrap items-center gap-1">
                            <x-admin.hidden-badge :item="$slide" />
                            @if ($state === 'upcoming')
                                <flux:badge size="sm" color="sky" icon="clock">{{ __('From :date', ['date' => $slide->starts_at->translatedFormat('d M Y H:i')]) }}</flux:badge>
                            @elseif ($state === 'ended')
                                <flux:badge size="sm" color="zinc">{{ __('Ended :date', ['date' => $slide->ends_at->translatedFormat('d M Y')]) }}</flux:badge>
                            @elseif ($slide->ends_at)
                                <flux:badge size="sm" color="emerald">{{ __('Until :date', ['date' => $slide->ends_at->translatedFormat('d M Y')]) }}</flux:badge>
                            @endif
                        </div>
                        @if ($slide->label)
                            <flux:badge size="sm" color="emerald" class="self-start">{{ $slide->label }}</flux:badge>
                        @endif
                        <div class="font-medium">{{ $slide->title ?: ($this->cards ? __('(no title)') : __('(no title: site name is shown)')) }}</div>
                        @if ($slide->button_label)
                            <div class="truncate text-xs text-zinc-500">{{ $slide->button_label }} → {{ $slide->button_url }}</div>
                        @endif
                        <div class="mt-auto flex items-center justify-end gap-1 pt-2">
                            @can('hero_slides.update')
                                <x-admin.visibility-toggle :item="$slide" />
                                <flux:button size="sm" variant="ghost" icon="arrow-left" wire:click="move('{{ $slide->id }}', -1)" :disabled="$loop->first" :aria-label="__('Earlier')" />
                                <flux:button size="sm" variant="ghost" icon="arrow-right" wire:click="move('{{ $slide->id }}', 1)" :disabled="$loop->last" :aria-label="__('Later')" />
                                <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit('{{ $slide->id }}')" />
                            @endcan
                            @can('hero_slides.delete')
                                <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete('{{ $slide->id }}')" wire:confirm="{{ __('Delete this slide? The image stays in the media library.') }}" />
                            @endcan
                        </div>
                    </div>
                </flux:card>
            @empty
                <flux:text class="col-span-full">{{ __('No slides yet. Without slides the home page shows the hero without pictures.') }}</flux:text>
            @endforelse
        </div>
    @endif

    <flux:modal name="slide-form" class="w-full md:w-[40rem]">
        <form wire:submit="save" class="space-y-4">
            <flux:heading size="lg">{{ $editingId ? __('Edit slide') : __('New slide') }}</flux:heading>
            <div wire:key="slide-media-{{ $formVersion }}">
                <livewire:admin.media-picker wire:model="imageId" kind="image" :label="__('Image')" :key="'slide-image-'.$formVersion" />
                <flux:text size="sm" class="mt-1">{{ $this->cards ? __('Landscape (4:3), at least 1200 px wide. The bottom is darkened behind the label and title.') : __('Landscape, at least 1600 px wide. The left side sits behind the text, so keep faces and details on the right.') }}</flux:text>
                <flux:error name="imageId" />
            </div>
            @if ($this->cards)
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model="label" :label="__('Label (optional)')" :placeholder="__('e.g. Prestasi Nasional')" />
                    <flux:input wire:model="buttonUrl" :label="__('Link (optional)')" placeholder="/berita/…" />
                </div>
                <flux:input wire:model="slideTitle" :label="__('Title (optional)')" />
            @else
                <flux:input wire:model="slideTitle" :label="__('Title (optional)')" :description="__('Shown instead of the site name while this slide is visible.')" />
                <flux:textarea wire:model="text" :label="__('Text (optional)')" rows="2" />
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model="buttonLabel" :label="__('Button text (optional)')" :placeholder="__('e.g. Daftar sekarang')" />
                    <flux:input wire:model="buttonUrl" :label="__('Button link')" placeholder="/penerimaan" />
                </div>
            @endif
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input type="datetime-local" wire:model="startsAt" :label="__('Show from (optional)')" />
                <flux:input type="datetime-local" wire:model="endsAt" :label="__('Show until (optional)')" />
            </div>
            <div class="flex justify-end"><flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button></div>
        </form>
    </flux:modal>
</div>
