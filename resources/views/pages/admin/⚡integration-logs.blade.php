<?php

use App\Models\IntegrationLog;
use App\Models\LegalQuestion;
use App\Siakad\TanyaHukum;
use Flux\Flux;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Integration log')] class extends Component {
    use WithPagination;

    #[Url]
    public string $status = '';

    #[Url]
    public string $direction = '';

    public ?string $viewingId = null;

    public function updated(string $property): void
    {
        if (in_array($property, ['status', 'direction'], true)) {
            $this->resetPage();
        }
    }

    /**
     * @return LengthAwarePaginator<int, IntegrationLog>
     */
    #[Computed]
    public function logs(): LengthAwarePaginator
    {
        return IntegrationLog::query()
            ->when(in_array($this->status, [IntegrationLog::SUCCESS, IntegrationLog::FAILED, IntegrationLog::REJECTED], true), fn ($query) => $query->where('status', $this->status))
            ->when(in_array($this->direction, [IntegrationLog::INBOUND, IntegrationLog::OUTBOUND], true), fn ($query) => $query->where('direction', $this->direction))
            ->latest()
            ->paginate(30);
    }

    #[Computed]
    public function viewing(): ?IntegrationLog
    {
        return $this->viewingId ? IntegrationLog::query()->find($this->viewingId) : null;
    }

    public function show(string $id): void
    {
        $this->viewingId = IntegrationLog::query()->findOrFail($id)->id;
        Flux::modal('log-detail')->show();
    }

    /**
     * Retry a failed outbound call (currently: forwarding a Tanya Hukum question).
     */
    public function retry(string $id, TanyaHukum $tanyaHukum): void
    {
        $this->authorize('integration_logs.retry');
        $log = IntegrationLog::query()->findOrFail($id);

        abort_unless($log->isRetryable() && $log->subject_type === 'legal_question', 422);

        $tanyaHukum->retryForward(LegalQuestion::query()->withoutGlobalScopes()->findOrFail($log->subject_id), Auth::user());

        Flux::modal('log-detail')->close();
        Flux::toast(variant: 'success', text: __('Queued again.'));
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Integration log') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Every call between the CMS and SIAKAD, including refused webhooks.') }}</flux:text>
    </div>

    <div class="flex flex-wrap gap-3">
        <flux:select wire:model.live="status" class="max-w-44">
            <option value="">{{ __('All statuses') }}</option>
            <option value="success">{{ __('Success') }}</option>
            <option value="failed">{{ __('Failed') }}</option>
            <option value="rejected">{{ __('Rejected') }}</option>
        </flux:select>
        <flux:select wire:model.live="direction" class="max-w-44">
            <option value="">{{ __('Both directions') }}</option>
            <option value="inbound">{{ __('SIAKAD → CMS') }}</option>
            <option value="outbound">{{ __('CMS → SIAKAD') }}</option>
        </flux:select>
    </div>

    <flux:table :paginate="$this->logs">
        <flux:table.columns>
            <flux:table.column>{{ __('When') }}</flux:table.column>
            <flux:table.column>{{ __('Direction') }}</flux:table.column>
            <flux:table.column>{{ __('Action') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column>HTTP</flux:table.column>
            <flux:table.column />
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->logs as $log)
                <flux:table.row :key="$log->id">
                    <flux:table.cell class="whitespace-nowrap">{{ $log->created_at?->translatedFormat('d M Y H:i:s') }}</flux:table.cell>
                    <flux:table.cell>{{ $log->direction === 'inbound' ? __('SIAKAD → CMS') : __('CMS → SIAKAD') }}</flux:table.cell>
                    <flux:table.cell class="font-mono text-xs">{{ $log->action }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="match ($log->status) { 'success' => 'emerald', 'failed' => 'red', default => 'amber' }">{{ $log->status }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>{{ $log->http_status }}</flux:table.cell>
                    <flux:table.cell align="end">
                        <flux:button size="sm" variant="ghost" icon="eye" wire:click="show('{{ $log->id }}')" />
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6">{{ __('No calls logged yet.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="log-detail" class="w-full md:w-[48rem]">
        @if ($log = $this->viewing)
            <div class="space-y-4">
                <flux:heading size="lg" class="font-mono">{{ $log->action }}</flux:heading>
                <flux:text>{{ $log->created_at?->translatedFormat('d M Y H:i:s') }} · {{ $log->status }} · HTTP {{ $log->http_status ?? '–' }}@if ($log->duration_ms) · {{ $log->duration_ms }} ms @endif</flux:text>
                @if ($log->external_id)
                    <flux:text>SIAKAD ID: <span class="font-mono">{{ $log->external_id }}</span></flux:text>
                @endif
                @if ($log->error)
                    <flux:callout icon="exclamation-triangle" color="red" :text="$log->error" />
                @endif
                @foreach (['request' => __('Request'), 'response' => __('Response')] as $key => $label)
                    @if ($log->{$key})
                        <div>
                            <flux:heading size="sm" class="mb-1">{{ $label }}</flux:heading>
                            <pre class="max-h-64 overflow-auto rounded-lg bg-zinc-100 p-3 text-xs dark:bg-zinc-800">{{ json_encode($log->{$key}, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                        </div>
                    @endif
                @endforeach
                @if ($log->isRetryable() && $log->subject_type === 'legal_question')
                    @can('integration_logs.retry')
                        <div class="flex justify-end"><flux:button variant="primary" icon="arrow-path" wire:click="retry('{{ $log->id }}')">{{ __('Retry') }}</flux:button></div>
                    @endcan
                @endif
            </div>
        @endif
    </flux:modal>
</div>
