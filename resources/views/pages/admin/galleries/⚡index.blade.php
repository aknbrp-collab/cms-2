<?php

use App\Enums\GalleryType;
use App\Models\Gallery;
use Flux\Flux;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Galleries')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $type = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Gallery::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'type'], true)) {
            $this->resetPage();
        }
    }

    /**
     * @return LengthAwarePaginator<int, Gallery>
     */
    #[Computed]
    public function galleries(): LengthAwarePaginator
    {
        return Gallery::query()
            ->with(['site', 'coverImage.media', 'items.mediaAsset.media'])
            ->withCount('items')
            ->when($this->search !== '', fn ($query) => $query->whereLike('title', "%{$this->search}%"))
            ->when(GalleryType::tryFrom($this->type), fn ($query, GalleryType $type) => $query->where('type', $type->value))
            ->latest('updated_at')
            ->paginate(24);
    }

    public function delete(string $id): void
    {
        $gallery = Gallery::query()->findOrFail($id);
        $this->authorize('delete', $gallery);

        $gallery->delete();

        Flux::toast(text: __('Gallery moved to trash.'));
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Galleries') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Photo albums and YouTube video collections.') }}</flux:text>
        </div>
        @can('create', App\Models\Gallery::class)
            <flux:button variant="primary" icon="plus" :href="route('admin.galleries.create')" wire:navigate>{{ __('New gallery') }}</flux:button>
        @endcan
    </div>

    <div class="flex flex-wrap gap-3">
        <flux:input wire:model.live.debounce.300ms="search" :placeholder="__('Search title')" icon="magnifying-glass" class="max-w-xs" />
        <flux:select wire:model.live="type" class="max-w-44">
            <option value="">{{ __('All types') }}</option>
            @foreach (GalleryType::cases() as $case)
                <option value="{{ $case->value }}">{{ $case->label() }}</option>
            @endforeach
        </flux:select>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        @forelse ($this->galleries as $gallery)
            @php
                $cover = $gallery->cover();
                $videoThumb = $gallery->type === GalleryType::Video ? $gallery->items->first()?->videoThumbnail() : null;
            @endphp
            <flux:card wire:key="gallery-{{ $gallery->id }}" class="overflow-hidden p-0!">
                <a href="{{ route('admin.galleries.edit', $gallery) }}" wire:navigate class="block aspect-[16/10] bg-zinc-100 dark:bg-zinc-800">
                    @if ($cover)
                        <img src="{{ $cover->url('thumb') }}" alt="" class="h-full w-full object-cover">
                    @elseif ($videoThumb)
                        <img src="{{ $videoThumb }}" alt="" class="h-full w-full object-cover">
                    @else
                        <div class="flex h-full items-center justify-center"><flux:icon :name="$gallery->type === GalleryType::Video ? 'film' : 'photo'" class="size-10 text-zinc-400" /></div>
                    @endif
                </a>
                <div class="space-y-2 p-4">
                    <div class="flex items-start justify-between gap-2">
                        <a href="{{ route('admin.galleries.edit', $gallery) }}" wire:navigate class="font-medium hover:underline">{{ $gallery->title }}</a>
                        <flux:badge size="sm" :color="$gallery->status->color()">{{ $gallery->status->label() }}</flux:badge>
                    </div>
                    <div class="flex items-center justify-between text-xs text-zinc-500">
                        <span>{{ $gallery->type->label() }} · {{ trans_choice(':count item|:count items', $gallery->items_count) }}@unless (current_site()) · {{ $gallery->site->name }}@endunless</span>
                        @can('delete', $gallery)
                            <flux:button size="xs" variant="ghost" icon="trash" wire:click="delete('{{ $gallery->id }}')" wire:confirm="{{ __('Move this gallery to the trash?') }}" />
                        @endcan
                    </div>
                </div>
            </flux:card>
        @empty
            <flux:text class="col-span-full">{{ __('No galleries yet.') }}</flux:text>
        @endforelse
    </div>

    {{ $this->galleries->links() }}
</div>
