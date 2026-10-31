<?php

use App\Livewire\ReferenceList;
use App\Models\Leader;
use App\Support\RichText;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;

new #[Title('Leadership')] class extends ReferenceList {
    public ?string $editingId = null;

    public string $name = '';

    public string $position = '';

    public string $expertise = '';

    public ?string $photoId = null;

    public string $bio = '';

    public int $sortOrder = 0;

    /** Bumped on every form reset so the rich text editor and picker re-mount. */
    public int $formVersion = 0;

    public function mount(): void
    {
        $this->authorize('leaders.view');
    }

    protected function module(): string
    {
        return 'leaders';
    }

    protected function findItem(string $id): Leader
    {
        return Leader::query()->with('site')->findOrFail($id);
    }

    /**
     * @return Collection<int, Leader>
     */
    #[Computed]
    public function leaders(): Collection
    {
        return Leader::query()->with(['site', 'photo.media'])->orderBy('sort_order')->orderBy('name')->get();
    }

    public function create(): void
    {
        $this->authorizeCreate();
        $this->resetForm();
        Flux::modal('leader-form')->show();
    }

    public function edit(string $id): void
    {
        $leader = $this->findItem($id);
        $this->authorizeItem($leader, 'update');

        $this->resetForm();
        $this->fill([
            'editingId' => $leader->id,
            'name' => $leader->name,
            'position' => $leader->position,
            'expertise' => (string) $leader->expertise,
            'photoId' => $leader->photo_id,
            'bio' => (string) $leader->bio,
            'sortOrder' => $leader->sort_order,
        ]);

        Flux::modal('leader-form')->show();
    }

    public function save(): void
    {
        if ($this->editingId) {
            $leader = $this->findItem($this->editingId);
            $this->authorizeItem($leader, 'update');
        } else {
            $leader = new Leader(['site_id' => $this->authorizeCreate()->id]);
        }

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'position' => ['required', 'string', 'max:255'],
            'expertise' => ['nullable', 'string', 'max:255'],
            'photoId' => ['nullable', 'exists:media_assets,id'],
            'bio' => ['nullable', 'string', 'max:20000'],
            'sortOrder' => ['integer', 'min:0', 'max:9999'],
        ]);

        $leader->fill([
            'name' => $this->name,
            'position' => $this->position,
            'expertise' => $this->expertise ?: null,
            'photo_id' => $this->photoId,
            'bio' => RichText::clean($this->bio) ?: null,
            'sort_order' => $this->sortOrder,
        ])->save();

        Flux::modal('leader-form')->close();
        Flux::toast(variant: 'success', text: __('Saved.'));
        $this->resetForm();
    }

    public function delete(string $id): void
    {
        $leader = $this->findItem($id);
        $this->authorizeItem($leader, 'delete');
        $leader->delete();

        Flux::toast(text: __('Deleted.'));
    }

    protected function resetForm(): void
    {
        $this->reset('editingId', 'name', 'position', 'expertise', 'photoId', 'bio', 'sortOrder');
        $this->resetValidation();
        $this->formVersion++;
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Leadership') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Pengasuh, Mudir, Dewan Masyayikh and other leaders shown on the website.') }}</flux:text>
        </div>
        @if (current_site())
            @can('leaders.create')
                <flux:button variant="primary" icon="plus" wire:click="create">{{ __('New profile') }}</flux:button>
            @endcan
        @endif
    </div>

    @if (! current_site())
        <x-admin.needs-site />
    @endif

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        @forelse ($this->leaders as $leader)
            <flux:card wire:key="leader-{{ $leader->id }}" class="flex flex-col gap-3">
                <div class="flex items-center gap-3">
                    @if ($leader->photo)
                        <img src="{{ $leader->photo->url('thumb') }}" alt="" class="size-14 rounded-full object-cover">
                    @else
                        <flux:avatar :name="$leader->name" size="lg" />
                    @endif
                    <div class="min-w-0">
                        <div class="truncate font-medium">{{ $leader->name }}</div>
                        <div class="truncate text-sm text-zinc-500">{{ $leader->position }}</div>
                    </div>
                </div>
                <div class="mt-auto flex items-center justify-between">
                    @can('leaders.publish')
                        <flux:switch :checked="$leader->is_published" wire:click="togglePublished('{{ $leader->id }}')" :label="__('Published')" />
                    @else
                        <flux:badge size="sm" :color="$leader->is_published ? 'emerald' : 'zinc'">{{ $leader->is_published ? __('Published') : __('Hidden') }}</flux:badge>
                    @endcan
                    <div>
                        @can('leaders.update')
                            <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit('{{ $leader->id }}')" />
                        @endcan
                        @can('leaders.delete')
                            <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete('{{ $leader->id }}')" wire:confirm="{{ __('Delete this profile?') }}" />
                        @endcan
                    </div>
                </div>
            </flux:card>
        @empty
            <flux:text class="col-span-full">{{ __('No profiles yet.') }}</flux:text>
        @endforelse
    </div>

    <flux:modal name="leader-form" class="w-full md:w-[40rem]">
        <form wire:submit="save" class="space-y-4">
            <flux:heading size="lg">{{ $editingId ? __('Edit profile') : __('New profile') }}</flux:heading>
            <flux:input wire:model="name" :label="__('Name')" required />
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="position" :label="__('Position')" :placeholder="__('e.g. Pengasuh, Mudir')" required />
                <flux:input wire:model="expertise" :label="__('Expertise / sanad')" />
            </div>
            <div wire:key="leader-media-{{ $formVersion }}" class="space-y-4">
                <livewire:admin.media-picker wire:model="photoId" :label="__('Photo')" :key="'leader-photo-'.$formVersion" />
                <x-admin.rich-text model="bio" :label="__('Biography')" />
            </div>
            <flux:input wire:model="sortOrder" type="number" min="0" :label="__('Order')" />
            <div class="flex justify-end"><flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button></div>
        </form>
    </flux:modal>
</div>
