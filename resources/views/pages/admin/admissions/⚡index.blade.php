<?php

use App\Livewire\ReferenceList;
use App\Models\AdmissionPeriod;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;

new #[Title('Admissions')] class extends ReferenceList {
    public function mount(): void
    {
        $this->authorize('admissions.view');
    }

    protected function module(): string
    {
        return 'admissions';
    }

    protected function findItem(string $id): AdmissionPeriod
    {
        return AdmissionPeriod::query()->with('site')->findOrFail($id);
    }

    /**
     * @return Collection<int, AdmissionPeriod>
     */
    #[Computed]
    public function periods(): Collection
    {
        return AdmissionPeriod::query()->with('site')->withCount('registrations')->latest('opens_on')->get();
    }

    public function delete(string $id): void
    {
        $period = $this->findItem($id);
        $this->authorizeItem($period, 'delete');

        if ($period->registrations()->exists()) {
            Flux::toast(variant: 'danger', text: __('This period has registrations and cannot be deleted. Unpublish it instead.'));

            return;
        }

        $period->delete();
        Flux::toast(text: __('Deleted.'));
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Admissions') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Admission periods (PSB / PMB) shown on the website, with optional online registration.') }}</flux:text>
        </div>
        <div class="flex gap-2">
            <flux:button icon="inbox" :href="route('admin.admissions.registrations')" wire:navigate>{{ __('Registrations') }}</flux:button>
            @if (current_site())
                @can('admissions.create')
                    <flux:button variant="primary" icon="plus" :href="route('admin.admissions.create')" wire:navigate>{{ __('New period') }}</flux:button>
                @endcan
            @endif
        </div>
    </div>

    @if (! current_site())
        <x-admin.needs-site />
    @endif

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Period') }}</flux:table.column>
            @unless (current_site())
                <flux:table.column>{{ __('Site') }}</flux:table.column>
            @endunless
            <flux:table.column>{{ __('Dates') }}</flux:table.column>
            <flux:table.column>{{ __('Online form') }}</flux:table.column>
            <flux:table.column>{{ __('Registrations') }}</flux:table.column>
            <flux:table.column>{{ __('Published') }}</flux:table.column>
            <flux:table.column />
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->periods as $period)
                <flux:table.row :key="$period->id">
                    <flux:table.cell>
                        <a href="{{ route('admin.admissions.edit', $period) }}" wire:navigate class="font-medium hover:underline">{{ $period->title }}</a>
                        <div class="text-xs text-zinc-500">{{ $period->academic_year }}</div>
                    </flux:table.cell>
                    @unless (current_site())
                        <flux:table.cell>{{ $period->site->name }}</flux:table.cell>
                    @endunless
                    <flux:table.cell class="whitespace-nowrap">
                        {{ $period->opens_on->translatedFormat('d M Y') }} – {{ $period->closes_on->translatedFormat('d M Y') }}
                        @if ($period->isOpen())
                            <flux:badge size="sm" color="emerald" class="ms-1">{{ __('Open') }}</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ $period->accepts_online_registration ? __('Yes') : __('No') }}</flux:table.cell>
                    <flux:table.cell>
                        <a href="{{ route('admin.admissions.registrations', ['period' => $period->id]) }}" wire:navigate class="hover:underline">{{ $period->registrations_count }}</a>
                    </flux:table.cell>
                    <flux:table.cell>
                        @can('admissions.publish')
                            <flux:switch :checked="$period->is_published" wire:click="togglePublished('{{ $period->id }}')" />
                        @else
                            {{ $period->is_published ? __('Yes') : __('No') }}
                        @endcan
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        @can('admissions.delete')
                            <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete('{{ $period->id }}')" wire:confirm="{{ __('Delete this period?') }}" />
                        @endcan
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7">{{ __('No admission periods yet.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
