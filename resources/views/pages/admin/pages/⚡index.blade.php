<?php

use App\Enums\ContentStatus;
use App\Livewire\ContentList;
use App\Models\Page;
use Flux\Flux;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

new #[Title('Pages')] class extends ContentList {
    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Page::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status'], true)) {
            $this->resetPage();
        }
    }

    /**
     * @return LengthAwarePaginator<int, Page>
     */
    #[Computed]
    public function pages(): LengthAwarePaginator
    {
        return Page::query()
            ->tap(fn ($query) => $this->applyVisibilityFilter($query))
            ->with(['site', 'parent'])
            ->when($this->search !== '', fn ($query) => $query->whereLike('title', "%{$this->search}%"))
            ->when(ContentStatus::tryFrom($this->status), fn ($query, ContentStatus $status) => $query->withStatus($status))
            ->orderBy('site_id')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->paginate(30);
    }

    protected function visibilityModel(): string
    {
        return Page::class;
    }

    public function delete(string $id): void
    {
        $page = Page::query()->findOrFail($id);
        $this->authorize('delete', $page);

        $page->delete();

        Flux::toast(text: __('Page moved to trash.'));
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Pages') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Profile pages such as About, History and Vision & Mission.') }}</flux:text>
        </div>
        @can('create', App\Models\Page::class)
            <flux:button variant="primary" icon="plus" :href="route('admin.pages.create')" wire:navigate>{{ __('New page') }}</flux:button>
        @endcan
    </div>

    <div class="flex flex-wrap gap-3">
        <flux:input wire:model.live.debounce.300ms="search" :placeholder="__('Search title')" icon="magnifying-glass" class="max-w-xs" />
        <flux:select wire:model.live="status" class="max-w-44">
            <option value="">{{ __('All statuses') }}</option>
            @foreach (ContentStatus::cases() as $case)
                <option value="{{ $case->value }}">{{ $case->label() }}</option>
            @endforeach
        </flux:select>
        <x-admin.visibility-filter />
    </div>

    <flux:table :paginate="$this->pages">
        <flux:table.columns>
            <flux:table.column>{{ __('Title') }}</flux:table.column>
            @unless (current_site())
                <flux:table.column>{{ __('Site') }}</flux:table.column>
            @endunless
            <flux:table.column>{{ __('URL') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column>{{ __('Updated') }}</flux:table.column>
            <flux:table.column />
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->pages as $page)
                <flux:table.row :key="$page->id">
                    <flux:table.cell>
                        @if ($page->parent)
                            <span class="text-zinc-400">{{ $page->parent->title }} /</span>
                        @endif
                        <a href="{{ route('admin.pages.edit', $page) }}" wire:navigate class="font-medium hover:underline">{{ $page->title }}</a>
                        <x-admin.hidden-badge :item="$page" class="ms-1" />
                    </flux:table.cell>
                    @unless (current_site())
                        <flux:table.cell>{{ $page->site->name }}</flux:table.cell>
                    @endunless
                    <flux:table.cell class="font-mono text-xs">/{{ $page->slug }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$page->status->color()">{{ $page->isScheduled() ? __('Scheduled') : $page->status->label() }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell class="whitespace-nowrap">{{ $page->updated_at?->diffForHumans() }}</flux:table.cell>
                    <flux:table.cell align="end">
                        <x-admin.visibility-toggle :item="$page" :can="auth()->user()->can('hide', $page)" />
                        @can('delete', $page)
                            <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete('{{ $page->id }}')" wire:confirm="{{ __('Move this page to the trash?') }}" />
                        @endcan
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6">{{ __('No pages found.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
