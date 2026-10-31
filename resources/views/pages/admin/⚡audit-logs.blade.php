<?php

use App\Models\AuditLog;
use App\Models\Site;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Audit logs')] class extends Component {
    use WithPagination;

    #[Url]
    public string $event = '';

    public function updatedEvent(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, AuditLog>
     */
    #[Computed]
    public function logs(): LengthAwarePaginator
    {
        return AuditLog::query()
            ->with('user')
            ->when(current_site(), fn ($query, Site $site) => $query->where('site_id', $site->id))
            ->when($this->event !== '', fn ($query) => $query->whereLike('event', $this->event.'%'))
            ->latest('created_at')
            ->paginate(25);
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Audit logs') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Append-only record of every change and sign-in.') }}</flux:text>
        </div>

        <flux:input wire:model.live.debounce.300ms="event" :placeholder="__('Filter by event, e.g. auth.')" icon="magnifying-glass" class="max-w-xs" />
    </div>

    <flux:table :paginate="$this->logs">
        <flux:table.columns>
            <flux:table.column>{{ __('When') }}</flux:table.column>
            <flux:table.column>{{ __('User') }}</flux:table.column>
            <flux:table.column>{{ __('Event') }}</flux:table.column>
            <flux:table.column>{{ __('Subject') }}</flux:table.column>
            <flux:table.column>{{ __('IP') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($this->logs as $log)
                <flux:table.row :key="$log->id">
                    <flux:table.cell class="whitespace-nowrap">{{ $log->created_at->format('Y-m-d H:i:s') }}</flux:table.cell>
                    <flux:table.cell>{{ $log->user?->name ?? __('System') }}</flux:table.cell>
                    <flux:table.cell><flux:badge size="sm">{{ $log->event }}</flux:badge></flux:table.cell>
                    <flux:table.cell>
                        @if ($log->auditable_type)
                            {{ class_basename($log->auditable_type) }}
                            <span class="text-xs text-zinc-500">{{ str($log->auditable_id)->limit(8, '') }}</span>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ $log->ip_address }}</flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
</div>
