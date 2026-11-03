<?php

use App\Enums\ContentStatus;
use App\Enums\PublicationType;
use App\Livewire\ContentList;
use App\Models\Publication;
use Flux\Flux;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

new #[Title('Publications')] class extends ContentList {
    #[Url]
    public string $search = '';

    #[Url]
    public string $type = '';

    #[Url]
    public string $status = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Publication::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'type', 'status'], true)) {
            $this->resetPage();
        }
    }

    /**
     * @return LengthAwarePaginator<int, Publication>
     */
    #[Computed]
    public function publications(): LengthAwarePaginator
    {
        return Publication::query()
            ->tap(fn ($query) => $this->applyVisibilityFilter($query))
            ->with(['site', 'journal'])
            ->when($this->search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->whereLike('title', "%{$this->search}%")
                ->orWhereLike('authors', "%{$this->search}%")))
            ->when(PublicationType::tryFrom($this->type), fn ($query, PublicationType $type) => $query->where('type', $type->value))
            ->when(ContentStatus::tryFrom($this->status), fn ($query, ContentStatus $status) => $query->withStatus($status))
            ->latest('updated_at')
            ->paginate(20);
    }

    protected function visibilityModel(): string
    {
        return Publication::class;
    }

    public function delete(string $id): void
    {
        $publication = Publication::query()->findOrFail($id);
        $this->authorize('delete', $publication);

        $publication->delete();

        Flux::toast(text: __('Publication moved to trash.'));
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Publications') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Research repository: articles, journal articles, Bahtsul Masail, theses and books.') }}</flux:text>
        </div>
        @can('create', App\Models\Publication::class)
            <flux:button variant="primary" icon="plus" :href="route('admin.publications.create')" wire:navigate>{{ __('New publication') }}</flux:button>
        @endcan
    </div>

    <div class="flex flex-wrap gap-3">
        <flux:input wire:model.live.debounce.300ms="search" :placeholder="__('Title or author')" icon="magnifying-glass" class="max-w-xs" />
        <flux:select wire:model.live="type" class="max-w-48">
            <option value="">{{ __('All types') }}</option>
            @foreach (PublicationType::cases() as $case)
                <option value="{{ $case->value }}">{{ $case->label() }}</option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="status" class="max-w-44">
            <option value="">{{ __('All statuses') }}</option>
            @foreach (ContentStatus::cases() as $case)
                <option value="{{ $case->value }}">{{ $case->label() }}</option>
            @endforeach
        </flux:select>
        <x-admin.visibility-filter />
    </div>

    <flux:table :paginate="$this->publications">
        <flux:table.columns>
            <flux:table.column>{{ __('Title') }}</flux:table.column>
            <flux:table.column>{{ __('Type') }}</flux:table.column>
            <flux:table.column>{{ __('Year') }}</flux:table.column>
            @unless (current_site())
                <flux:table.column>{{ __('Site') }}</flux:table.column>
            @endunless
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column />
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->publications as $publication)
                <flux:table.row :key="$publication->id">
                    <flux:table.cell class="max-w-md">
                        <a href="{{ route('admin.publications.edit', $publication) }}" wire:navigate class="font-medium hover:underline">{{ $publication->title }}</a>
                        <x-admin.hidden-badge :item="$publication" class="ms-1" />
                        <div class="truncate text-xs text-zinc-500">{{ $publication->authors }}</div>
                    </flux:table.cell>
                    <flux:table.cell>{{ $publication->type->label() }}</flux:table.cell>
                    <flux:table.cell>{{ $publication->year }}</flux:table.cell>
                    @unless (current_site())
                        <flux:table.cell>{{ $publication->site->name }}</flux:table.cell>
                    @endunless
                    <flux:table.cell><flux:badge size="sm" :color="$publication->status->color()">{{ $publication->status->label() }}</flux:badge></flux:table.cell>
                    <flux:table.cell align="end">
                        <x-admin.visibility-toggle :item="$publication" :can="auth()->user()->can('hide', $publication)" />
                        @can('delete', $publication)
                            <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete('{{ $publication->id }}')" wire:confirm="{{ __('Move this publication to the trash?') }}" />
                        @endcan
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6">{{ __('No publications found.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
