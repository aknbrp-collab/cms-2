<?php

use App\Jobs\RunBackup;
use App\Services\Backups;
use App\Support\Audit;
use Flux\Flux;
use Illuminate\Support\Number;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Data & backups')] class extends Component {
    public function mount(): void
    {
        $this->authorize('data.view');
    }

    /**
     * @return list<array{disk: string, reachable: bool, error: string|null, used: float, backups: list<array{path: string, file: string, date: \Carbon\CarbonInterface, size: float}>}>
     */
    #[Computed]
    public function destinations(): array
    {
        return app(Backups::class)->destinations();
    }

    /**
     * @return array{state: string, only_db: bool, started_at: string, finished_at?: string, message?: string|null}|null
     */
    #[Computed]
    public function status(): ?array
    {
        return app(Backups::class)->status();
    }

    #[Computed]
    public function running(): bool
    {
        return app(Backups::class)->running();
    }

    public function backUp(bool $onlyDb = false): void
    {
        $this->authorize('data.backup');
        $backups = app(Backups::class);

        if ($backups->running()) {
            Flux::toast(variant: 'warning', text: __('A backup is already running.'));

            return;
        }

        $backups->setStatus(['state' => 'running', 'only_db' => $onlyDb, 'started_at' => now()->toIso8601String()]);
        Audit::record('data.backup_started', meta: ['only_db' => $onlyDb]);
        RunBackup::dispatch($onlyDb);

        unset($this->status, $this->running, $this->destinations);
        Flux::toast(variant: 'success', text: __('Backup started. It runs in the background; this page updates when it is done.'));
    }

    public function deleteBackup(string $disk, string $path): void
    {
        $this->authorize('data.backup');
        $backup = app(Backups::class)->find($disk, $path) ?? abort(404);

        $backup->delete();
        Audit::record('data.backup_deleted', meta: ['disk' => $disk, 'file' => basename($path)]);

        unset($this->destinations);
        Flux::toast(text: __('Backup deleted.'));
    }

    /**
     * Polled while a backup runs.
     */
    public function refreshStatus(): void
    {
        unset($this->status, $this->running);

        if (! $this->running) {
            unset($this->destinations);
        }
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6" @if ($this->running) wire:poll.5s="refreshStatus" @endif>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Data & backups') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Backups hold the database and uploaded files of both sites, encrypted. They also run automatically every night on the server.') }}</flux:text>
        </div>
        @can('data.backup')
            <div class="flex flex-wrap gap-2">
                <flux:button icon="circle-stack" wire:click="backUp(true)" :disabled="$this->running">{{ __('Back up database') }}</flux:button>
                <flux:button variant="primary" icon="archive-box-arrow-down" wire:click="backUp(false)" :disabled="$this->running">{{ __('Back up everything') }}</flux:button>
            </div>
        @endcan
    </div>

    @if ($status = $this->status)
        @if ($this->running)
            <flux:callout icon="arrow-path" color="sky" :heading="__('Backup running…')" :text="__('Started :time. You can leave this page; the backup continues.', ['time' => \Illuminate\Support\Carbon::parse($status['started_at'])->diffForHumans()])" />
        @elseif ($status['state'] === 'failed')
            <flux:callout icon="exclamation-triangle" color="red" :heading="__('The last backup failed')" :text="$status['message'] ?? ''" />
        @elseif ($status['state'] === 'done' && isset($status['finished_at']))
            <flux:callout icon="check-circle" color="emerald" :text="__('The last backup finished :time.', ['time' => \Illuminate\Support\Carbon::parse($status['finished_at'])->diffForHumans()])" />
        @endif
    @endif

    @foreach ($this->destinations as $destination)
        <flux:card wire:key="disk-{{ $destination['disk'] }}" class="space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <flux:heading class="flex items-center gap-2">
                    <flux:icon.server-stack class="size-5 text-zinc-500" />
                    {{ $destination['disk'] === 'local' ? __('This server') : __('Storage ":disk"', ['disk' => $destination['disk']]) }}
                </flux:heading>
                @if ($destination['reachable'])
                    <flux:text size="sm">{{ trans_choice(':count backup|:count backups', count($destination['backups'])) }} · {{ Number::fileSize($destination['used'], precision: 1) }}</flux:text>
                @endif
            </div>

            @if (! $destination['reachable'])
                <flux:callout icon="exclamation-triangle" color="amber" :heading="__('Storage not reachable')" :text="$destination['error'] ?? ''" />
            @elseif ($destination['backups'] === [])
                <flux:text>{{ __('No backups yet.') }}</flux:text>
            @else
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Date') }}</flux:table.column>
                        <flux:table.column>{{ __('File') }}</flux:table.column>
                        <flux:table.column>{{ __('Size') }}</flux:table.column>
                        <flux:table.column />
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($destination['backups'] as $backup)
                            <flux:table.row :key="$destination['disk'].'-'.$backup['path']">
                                <flux:table.cell class="whitespace-nowrap">{{ $backup['date']->translatedFormat('d M Y H:i') }} <span class="text-xs text-zinc-500">· {{ $backup['date']->diffForHumans() }}</span></flux:table.cell>
                                <flux:table.cell class="font-mono text-xs">{{ $backup['file'] }}</flux:table.cell>
                                <flux:table.cell class="whitespace-nowrap">{{ Number::fileSize($backup['size'], precision: 1) }}</flux:table.cell>
                                <flux:table.cell align="end" class="whitespace-nowrap">
                                    @can('data.backup')
                                        <flux:button size="sm" variant="ghost" icon="arrow-down-tray" :href="route('admin.data.download', ['disk' => $destination['disk'], 'path' => $backup['path']])" :tooltip="__('Download')" />
                                        <flux:button
                                            size="sm"
                                            variant="ghost"
                                            icon="trash"
                                            wire:click="deleteBackup(@js($destination['disk']), @js($backup['path']))"
                                            wire:confirm.prompt="{{ __('Delete this backup permanently? It cannot be recovered. Type DELETE to confirm.') }}|DELETE"
                                            :tooltip="__('Delete')"
                                        />
                                    @endcan
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </flux:card>
    @endforeach

    <flux:text size="sm" class="max-w-3xl">{{ __('Downloaded backups are encrypted with the backup password (BACKUP_ARCHIVE_PASSWORD on the server). Keep that password in the password manager: without it a backup cannot be opened.') }}</flux:text>
</div>
