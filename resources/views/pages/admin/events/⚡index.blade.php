<?php

use App\Enums\ContentStatus;
use App\Livewire\ContentList;
use App\Models\Event;
use Flux\Flux;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

new #[Title('Agenda')] class extends ContentList {
    #[Url]
    public string $search = '';

    #[Url]
    public string $when = 'upcoming';

    public function mount(): void
    {
        $this->authorize('viewAny', Event::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'when'], true)) {
            $this->resetPage();
        }
    }

    /**
     * @return LengthAwarePaginator<int, Event>
     */
    #[Computed]
    public function events(): LengthAwarePaginator
    {
        return Event::query()
            ->tap(fn ($query) => $this->applyVisibilityFilter($query))
            ->with('site')
            ->when($this->search !== '', fn ($query) => $query->whereLike('title', "%{$this->search}%"))
            ->when($this->when === 'upcoming', fn ($query) => $query->upcoming()->orderBy('starts_at'))
            ->when($this->when === 'past', fn ($query) => $query->where('starts_at', '<', now()->startOfDay())->orderByDesc('starts_at'))
            ->paginate(20);
    }

    protected function visibilityModel(): string
    {
        return Event::class;
    }

    public function delete(string $id): void
    {
        $event = Event::query()->findOrFail($id);
        $this->authorize('delete', $event);

        $event->delete();

        Flux::toast(text: __('Event moved to trash.'));
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Agenda') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Events, halaqah, exams and holidays shown on the website calendar.') }}</flux:text>
        </div>
        @can('create', App\Models\Event::class)
            <flux:button variant="primary" icon="plus" :href="route('admin.events.create')" wire:navigate>{{ __('New event') }}</flux:button>
        @endcan
    </div>

    <div class="flex flex-wrap gap-3">
        <flux:input wire:model.live.debounce.300ms="search" :placeholder="__('Search title')" icon="magnifying-glass" class="max-w-xs" />
        <flux:select wire:model.live="when" class="max-w-44">
            <option value="upcoming">{{ __('Upcoming') }}</option>
            <option value="past">{{ __('Past') }}</option>
        </flux:select>
        <x-admin.visibility-filter />
    </div>

    <flux:table :paginate="$this->events">
        <flux:table.columns>
            <flux:table.column>{{ __('Date') }}</flux:table.column>
            <flux:table.column>{{ __('Title') }}</flux:table.column>
            @unless (current_site())
                <flux:table.column>{{ __('Site') }}</flux:table.column>
            @endunless
            <flux:table.column>{{ __('Location') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column />
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->events as $event)
                <flux:table.row :key="$event->id">
                    <flux:table.cell class="whitespace-nowrap">{{ $event->starts_at->translatedFormat($event->all_day ? 'd M Y' : 'd M Y H:i') }}</flux:table.cell>
                    <flux:table.cell>
                        <a href="{{ route('admin.events.edit', $event) }}" wire:navigate class="font-medium hover:underline">{{ $event->title }}</a>
                        <x-admin.hidden-badge :item="$event" class="ms-1" />
                        @if ($event->label)
                            <div class="text-xs text-zinc-500">{{ $event->label }}</div>
                        @endif
                    </flux:table.cell>
                    @unless (current_site())
                        <flux:table.cell>{{ $event->site->name }}</flux:table.cell>
                    @endunless
                    <flux:table.cell>{{ $event->location }}</flux:table.cell>
                    <flux:table.cell><flux:badge size="sm" :color="$event->status->color()">{{ $event->isScheduled() ? __('Scheduled') : $event->status->label() }}</flux:badge></flux:table.cell>
                    <flux:table.cell align="end">
                        <x-admin.visibility-toggle :item="$event" :can="auth()->user()->can('hide', $event)" />
                        @can('delete', $event)
                            <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete('{{ $event->id }}')" wire:confirm="{{ __('Move this event to the trash?') }}" />
                        @endcan
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6">{{ __('No events found.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
