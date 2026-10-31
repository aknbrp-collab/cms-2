<?php

use App\Models\MediaAsset;
use App\Support\MediaUploader;
use Flux\Flux;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new #[Title('Media library')] class extends Component {
    use WithFileUploads, WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $kind = '';

    /** @var list<UploadedFile> */
    public array $uploads = [];

    public bool $shared = false;

    public ?string $editingId = null;

    public string $title = '';

    public string $alt = '';

    public string $caption = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedKind(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, MediaAsset>
     */
    #[Computed]
    public function assets(): LengthAwarePaginator
    {
        return MediaAsset::query()
            ->with(['media', 'site'])
            ->when($this->search !== '', fn ($query) => $query->whereLike('title', "%{$this->search}%"))
            ->when($this->kind !== '', fn ($query) => $query->whereHas('media', fn ($query) => match ($this->kind) {
                'image' => $query->whereIn('mime_type', MediaAsset::IMAGE_TYPES),
                'video' => $query->whereLike('mime_type', 'video/%'),
                default => $query->whereNotIn('mime_type', MediaAsset::IMAGE_TYPES)->whereNotLike('mime_type', 'video/%'),
            }))
            ->latest()
            ->paginate(24);
    }

    public function updatedUploads(MediaUploader $uploader): void
    {
        $this->authorize('media.create');

        $this->validate([
            'uploads' => ['array', 'max:20'],
            'uploads.*' => ['file', 'max:12288', 'extensions:'.implode(',', MediaAsset::ACCEPTED_EXTENSIONS)],
        ]);

        $shared = $this->shared || current_site() === null;
        abort_if($shared && ! Auth::user()->canAccessAllSites(), 403);

        foreach ($this->uploads as $file) {
            $uploader->store($file, Auth::user(), $shared);
        }

        Flux::toast(variant: 'success', text: trans_choice(':count file uploaded.|:count files uploaded.', count($this->uploads)));
        $this->reset('uploads');
        $this->resetPage();
    }

    public function edit(string $id): void
    {
        $asset = MediaAsset::query()->findOrFail($id);
        $this->authorize('update', $asset);

        $this->editingId = $asset->id;
        $this->title = $asset->title;
        $this->alt = (string) $asset->alt;
        $this->caption = (string) $asset->caption;

        Flux::modal('asset-form')->show();
    }

    public function save(): void
    {
        $asset = MediaAsset::query()->findOrFail($this->editingId);
        $this->authorize('update', $asset);

        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'alt' => ['nullable', 'string', 'max:255'],
            'caption' => ['nullable', 'string', 'max:1000'],
        ]);

        $asset->update($validated);

        Flux::modal('asset-form')->close();
        Flux::toast(variant: 'success', text: __('Saved.'));
    }

    public function delete(string $id): void
    {
        $asset = MediaAsset::query()->findOrFail($id);
        $this->authorize('delete', $asset);

        $asset->delete();

        Flux::modal('asset-form')->close();
        Flux::toast(text: __('Deleted.'));
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Media library') }}</flux:heading>
            <flux:text class="mt-1">
                {{ current_site() ? __('Files of :site and files shared by all sites.', ['site' => current_site()->name]) : __('Files of every site.') }}
            </flux:text>
        </div>

        @can('media.create')
            <div class="flex flex-col items-end gap-2">
                <flux:input type="file" wire:model="uploads" multiple :label="__('Upload files')" />
                @if (current_site() && auth()->user()->canAccessAllSites())
                    <flux:checkbox wire:model="shared" :label="__('Share with all sites')" />
                @endif
                <div wire:loading wire:target="uploads"><flux:text size="sm">{{ __('Uploading…') }}</flux:text></div>
                <flux:error name="uploads" />
                <flux:error name="uploads.*" />
                <flux:error name="upload" />
            </div>
        @endcan
    </div>

    <div class="flex flex-wrap gap-3">
        <flux:input wire:model.live.debounce.300ms="search" :placeholder="__('Search')" icon="magnifying-glass" class="max-w-xs" />
        <flux:select wire:model.live="kind" class="max-w-40">
            <option value="">{{ __('All types') }}</option>
            <option value="image">{{ __('Images') }}</option>
            <option value="video">{{ __('Videos') }}</option>
            <option value="document">{{ __('Documents') }}</option>
        </flux:select>
    </div>

    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-6">
        @forelse ($this->assets as $asset)
            <button type="button" wire:key="asset-{{ $asset->id }}" wire:click="edit('{{ $asset->id }}')" class="overflow-hidden rounded-lg border border-zinc-200 text-start hover:border-zinc-400 dark:border-zinc-700">
                @if ($asset->isImage())
                    <img src="{{ $asset->url('thumb') }}" alt="{{ $asset->alt }}" class="aspect-[4/3] w-full object-cover" loading="lazy">
                @else
                    <div class="flex aspect-[4/3] items-center justify-center bg-zinc-100 dark:bg-zinc-800">
                        <flux:icon name="{{ str_starts_with((string) $asset->file()?->mime_type, 'video/') ? 'film' : 'document' }}" class="size-10 text-zinc-400" />
                    </div>
                @endif
                <div class="px-2 py-1.5">
                    <div class="truncate text-sm font-medium">{{ $asset->title }}</div>
                    <div class="text-xs text-zinc-500">{{ $asset->site?->name ?? __('Shared') }}</div>
                </div>
            </button>
        @empty
            <flux:text class="col-span-full">{{ __('No files yet.') }}</flux:text>
        @endforelse
    </div>

    {{ $this->assets->links() }}

    <flux:modal name="asset-form" class="md:w-[32rem]">
        @if ($editingId && ($asset = \App\Models\MediaAsset::query()->find($editingId)))
            <form wire:submit="save" class="space-y-4">
                <flux:heading size="lg">{{ __('File details') }}</flux:heading>

                @if ($asset->isImage())
                    <img src="{{ $asset->url('large') }}" alt="{{ $asset->alt }}" class="max-h-64 w-full rounded-lg object-contain">
                @endif

                <flux:input :label="__('URL')" :value="$asset->url()" readonly copyable />
                <flux:input wire:model="title" :label="__('Title')" required />
                <flux:input wire:model="alt" :label="__('Alt text')" :description="__('Describe the image for screen readers and search engines.')" />
                <flux:textarea wire:model="caption" :label="__('Caption')" rows="2" />

                <div class="flex justify-between">
                    @can('delete', $asset)
                        <flux:button variant="danger" wire:click="delete('{{ $asset->id }}')" wire:confirm="{{ __('Delete this file? Content using it will lose the image.') }}">{{ __('Delete') }}</flux:button>
                    @endcan
                    <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                </div>
            </form>
        @endif
    </flux:modal>
</div>
