<?php

use App\Models\MediaAsset;
use App\Support\MediaUploader;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Modelable;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Pick an image from the media library (or upload one) and bind its id.
 */
new class extends Component {
    use WithFileUploads;

    #[Modelable]
    public ?string $value = null;

    public string $label = '';

    public string $search = '';

    /** @var UploadedFile|null */
    public $upload = null;

    #[Computed]
    public function selected(): ?MediaAsset
    {
        return $this->value ? MediaAsset::query()->with('media')->find($this->value) : null;
    }

    /**
     * @return Collection<int, MediaAsset>
     */
    #[Computed]
    public function assets(): Collection
    {
        return MediaAsset::query()
            ->with('media')
            ->whereHas('media', fn ($query) => $query->whereIn('mime_type', MediaAsset::IMAGE_TYPES))
            ->when($this->search !== '', fn ($query) => $query->whereLike('title', "%{$this->search}%"))
            ->latest()
            ->limit(48)
            ->get();
    }

    public function choose(string $id): void
    {
        $this->value = MediaAsset::query()->findOrFail($id)->id;
        $this->modal('media-picker-'.$this->getId())->close();
    }

    public function clear(): void
    {
        $this->value = null;
    }

    public function updatedUpload(MediaUploader $uploader): void
    {
        $this->authorize('media.create');
        $this->validate(['upload' => ['image', 'max:12288']]);

        $asset = $uploader->store($this->upload, Auth::user());
        $this->reset('upload');
        $this->choose($asset->id);
    }
}; ?>

<flux:field>
    @if ($label)
        <flux:label>{{ $label }}</flux:label>
    @endif

    <div class="flex items-center gap-3">
        @if ($this->selected)
            <img src="{{ $this->selected->url('thumb') }}" alt="{{ $this->selected->alt }}" class="size-20 rounded-lg border border-zinc-200 object-cover dark:border-zinc-700">
            <div class="flex flex-col gap-1">
                <flux:text size="sm" class="max-w-48 truncate">{{ $this->selected->title }}</flux:text>
                <div class="flex gap-1">
                    <flux:modal.trigger :name="'media-picker-'.$this->getId()">
                        <flux:button size="xs">{{ __('Change') }}</flux:button>
                    </flux:modal.trigger>
                    <flux:button size="xs" variant="ghost" wire:click="clear">{{ __('Remove') }}</flux:button>
                </div>
            </div>
        @else
            <flux:modal.trigger :name="'media-picker-'.$this->getId()">
                <flux:button size="sm" icon="photo">{{ __('Choose image') }}</flux:button>
            </flux:modal.trigger>
        @endif
    </div>

    <flux:modal :name="'media-picker-'.$this->getId()" class="w-full max-w-4xl">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Media library') }}</flux:heading>

            <div class="flex flex-wrap items-end gap-3">
                <flux:input wire:model.live.debounce.300ms="search" :placeholder="__('Search images')" icon="magnifying-glass" class="max-w-xs" />
                @can('media.create')
                    <flux:input type="file" wire:model="upload" accept="image/*" :label="__('Upload new')" class="max-w-xs" />
                @endcan
            </div>
            <div wire:loading wire:target="upload"><flux:text size="sm">{{ __('Uploading…') }}</flux:text></div>
            <flux:error name="upload" />

            <div class="grid max-h-[60vh] grid-cols-3 gap-3 overflow-y-auto sm:grid-cols-4 md:grid-cols-6">
                @forelse ($this->assets as $asset)
                    <button
                        type="button"
                        wire:key="pick-{{ $asset->id }}"
                        wire:click="choose('{{ $asset->id }}')"
                        @class([
                            'group overflow-hidden rounded-lg border text-start',
                            'border-emerald-600 ring-2 ring-emerald-600' => $asset->id === $value,
                            'border-zinc-200 dark:border-zinc-700' => $asset->id !== $value,
                        ])
                    >
                        <img src="{{ $asset->url('thumb') }}" alt="{{ $asset->alt }}" class="aspect-[4/3] w-full object-cover" loading="lazy">
                        <span class="block truncate px-1.5 py-1 text-xs">{{ $asset->title }}</span>
                    </button>
                @empty
                    <flux:text class="col-span-full">{{ __('No images yet.') }}</flux:text>
                @endforelse
            </div>
        </div>
    </flux:modal>
</flux:field>
