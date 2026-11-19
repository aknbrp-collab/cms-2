<?php

use App\Jobs\PurgeData;
use App\Jobs\RestoreBackup;
use App\Jobs\RunBackup;
use App\Services\Backups;
use App\Services\DataPurge;
use App\Services\Restore\BackupRestorer;
use App\Services\Restore\RestoreStatus;
use App\Support\Audit;
use Flux\Flux;
use Illuminate\Foundation\Http\MaintenanceModeBypassCookie;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Data & backups')] class extends Component {
    /** Backup chosen for restoring: disk and path. */
    public string $restoreDisk = '';

    public string $restorePath = '';

    public string $restorePassword = '';

    public string $restoreConfirmation = '';

    /** Clean-up: what (see DataPurge::KINDS) and the cut-off date. */
    public string $purgeKind = 'admissions';

    public string $purgeBefore = '';

    /** @var array{total: int, details: array<string, int>, periods: list<array{title: string, site: string, closes_on: string, registrations: int}>}|null */
    public ?array $purgePreview = null;

    public string $purgePassword = '';

    public string $purgeConfirmation = '';

    public function mount(): void
    {
        $this->authorize('data.view');
        $this->purgeBefore = $this->defaultCutOff($this->purgeKind);
    }

    /**
     * @return array<string, mixed>|null
     */
    #[Computed]
    public function purgeStatus(): ?array
    {
        return PurgeData::status();
    }

    #[Computed]
    public function purging(): bool
    {
        return PurgeData::running();
    }

    public function updatedPurgeKind(): void
    {
        $this->purgeBefore = $this->defaultCutOff($this->purgeKind);
        $this->reset('purgePreview', 'purgePassword', 'purgeConfirmation');
    }

    public function updatedPurgeBefore(): void
    {
        $this->reset('purgePreview', 'purgePassword', 'purgeConfirmation');
    }

    public function previewPurge(): void
    {
        $this->authorize('data.purge');
        $this->validatePurgeChoice();

        $this->purgePreview = app(DataPurge::class)->preview($this->purgeKind, Carbon::parse($this->purgeBefore)->startOfDay());
    }

    public function startPurge(): void
    {
        $this->authorize('data.purge');
        $this->validatePurgeChoice();
        $this->validate([
            'purgePassword' => ['required', 'current_password'],
            'purgeConfirmation' => ['required', 'in:DELETE'],
        ], ['purgeConfirmation.in' => __('Type DELETE in capitals.')], ['purgePassword' => __('password'), 'purgeConfirmation' => __('confirmation')]);

        if ($this->purging || $this->restoring || app(Backups::class)->running()) {
            $this->addError('purgeConfirmation', __('A backup, restore or clean-up is already running.'));

            return;
        }

        $actor = Auth::user()->name;
        PurgeData::setStatus(['state' => 'queued', 'kind' => $this->purgeKind, 'before' => $this->purgeBefore, 'by' => $actor, 'started_at' => now()->toIso8601String()]);
        Audit::record('data.purge_started', meta: ['kind' => $this->purgeKind, 'before' => $this->purgeBefore]);
        PurgeData::dispatch($this->purgeKind, $this->purgeBefore, $actor);

        $this->reset('purgePreview', 'purgePassword', 'purgeConfirmation');
        unset($this->purgeStatus, $this->purging);
        Flux::toast(variant: 'success', text: __('Clean-up started. A backup is taken first.'));
    }

    protected function validatePurgeChoice(): void
    {
        $this->validate([
            'purgeKind' => ['required', 'in:'.implode(',', DataPurge::KINDS)],
            'purgeBefore' => ['required', 'date', 'before_or_equal:today'],
        ], [], ['purgeBefore' => __('date')]);
    }

    protected function defaultCutOff(string $kind): string
    {
        return match ($kind) {
            'integration_logs' => today()->subDays((int) config('cms.retention.integration_logs'))->toDateString(),
            default => today()->subYear()->toDateString(),
        };
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

    /**
     * @return array<string, mixed>|null
     */
    #[Computed]
    public function restoreStatus(): ?array
    {
        return app(RestoreStatus::class)->get();
    }

    #[Computed]
    public function restoring(): bool
    {
        return app(RestoreStatus::class)->running();
    }

    /**
     * @return list<array{label: string, ok: bool, message: string}>
     */
    #[Computed]
    public function restoreChecks(): array
    {
        return app(BackupRestorer::class)->checks();
    }

    /**
     * What the admin types to confirm: the backup's file name without ".zip".
     */
    #[Computed]
    public function restoreCode(): string
    {
        return Str::beforeLast(basename($this->restorePath), '.zip');
    }

    public function chooseRestore(string $disk, string $path): void
    {
        $this->authorize('data.restore');
        app(Backups::class)->find($disk, $path) ?? abort(404);

        $this->fill(['restoreDisk' => $disk, 'restorePath' => $path, 'restorePassword' => '', 'restoreConfirmation' => '']);
        $this->resetValidation();
        unset($this->restoreChecks, $this->restoreCode);
        Flux::modal('restore-backup')->show();
    }

    public function startRestore(): void
    {
        $this->authorize('data.restore');
        app(Backups::class)->find($this->restoreDisk, $this->restorePath) ?? abort(404);

        $this->validate([
            'restorePassword' => ['required', 'current_password'],
            'restoreConfirmation' => ['required', 'in:'.$this->restoreCode],
        ], [
            'restoreConfirmation.in' => __('Type the backup name exactly as shown.'),
        ], [
            'restorePassword' => __('password'),
            'restoreConfirmation' => __('confirmation'),
        ]);

        if (! app(BackupRestorer::class)->ready()) {
            $this->addError('restoreConfirmation', __('The server is not ready for a restore; see the checks above.'));

            return;
        }

        if ($this->restoring || $this->purging || app(Backups::class)->running()) {
            $this->addError('restoreConfirmation', __('A backup, restore or clean-up is already running.'));

            return;
        }

        // The admin keeps access while the site is in maintenance mode.
        $secret = Str::random(40);
        cookie()->queue(MaintenanceModeBypassCookie::create($secret));

        $actor = Auth::user()->name;
        app(RestoreStatus::class)->update(['state' => 'queued', 'file' => basename($this->restorePath), 'by' => $actor, 'started_at' => now()->toIso8601String(), 'message' => null, 'finished_at' => null]);
        Audit::record('data.restore_started', meta: ['disk' => $this->restoreDisk, 'file' => basename($this->restorePath)]);
        RestoreBackup::dispatch($this->restoreDisk, $this->restorePath, $secret, $actor);

        $this->reset('restorePassword', 'restoreConfirmation');
        unset($this->restoreStatus, $this->restoring);
        Flux::modal('restore-backup')->close();
        Flux::toast(variant: 'success', text: __('Restore started. Follow the progress on this page.'));
    }

    public function backUp(bool $onlyDb = false): void
    {
        $this->authorize('data.backup');
        $backups = app(Backups::class);

        if ($this->restoring || $this->purging) {
            Flux::toast(variant: 'warning', text: __('A restore or clean-up is running.'));

            return;
        }

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
        unset($this->status, $this->running, $this->restoreStatus, $this->restoring, $this->purgeStatus, $this->purging);

        if (! $this->running && ! $this->restoring) {
            unset($this->destinations);
        }
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6" @if ($this->running || $this->restoring || $this->purging) wire:poll.3s="refreshStatus" @endif>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Data & backups') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Backups hold the database and uploaded files of both sites, encrypted. They also run automatically every night on the server.') }}</flux:text>
        </div>
        @can('data.backup')
            <div class="flex flex-wrap gap-2">
                <flux:button icon="circle-stack" wire:click="backUp(true)" :disabled="$this->running || $this->restoring">{{ __('Back up database') }}</flux:button>
                <flux:button variant="primary" icon="archive-box-arrow-down" wire:click="backUp(false)" :disabled="$this->running || $this->restoring">{{ __('Back up everything') }}</flux:button>
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

    @if ($restore = $this->restoreStatus)
        @include('pages.admin.partials.restore-progress', ['restore' => $restore, 'restoring' => $this->restoring])
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
                                    @can('data.restore')
                                        <flux:button size="sm" variant="ghost" icon="arrow-uturn-left" wire:click="chooseRestore(@js($destination['disk']), @js($backup['path']))" :disabled="$this->restoring || $this->running">{{ __('Restore') }}</flux:button>
                                    @endcan
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

    @can('data.purge')
        @include('pages.admin.partials.data-purge')
    @endcan

    <flux:modal name="restore-backup" class="w-full md:w-[36rem]">
        <form wire:submit="startRestore" class="space-y-4">
            <flux:heading size="lg">{{ __('Restore :name', ['name' => $this->restoreCode]) }}</flux:heading>
            <flux:callout icon="exclamation-triangle" color="red" :text="__('All current data of both sites (database and uploaded files) is replaced by this backup. Anything changed since the backup was made is lost. A backup of the current data is taken first, and the sites are in maintenance mode for a few minutes.')" />

            <div class="space-y-1">
                @foreach ($this->restoreChecks as $check)
                    <div class="flex items-start gap-2 text-sm">
                        @if ($check['ok'])
                            <flux:icon.check-circle class="size-5 shrink-0 text-emerald-600" />
                        @else
                            <flux:icon.x-circle class="size-5 shrink-0 text-red-600" />
                        @endif
                        <span><span class="font-medium">{{ $check['label'] }}</span> <span class="text-zinc-500">· {{ $check['message'] }}</span></span>
                    </div>
                @endforeach
            </div>

            <flux:input type="password" wire:model="restorePassword" :label="__('Your password')" autocomplete="current-password" />
            <flux:input wire:model="restoreConfirmation" :label="__('Type :code to confirm', ['code' => $this->restoreCode])" autocomplete="off" class="font-mono" />
            <flux:text size="sm">{{ __('You may need to sign in again afterwards.') }}</flux:text>

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="danger" icon="arrow-uturn-left">{{ __('Restore this backup') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:text size="sm" class="max-w-3xl">{{ __('Downloaded backups are encrypted with the backup password (BACKUP_ARCHIVE_PASSWORD on the server). Keep that password in the password manager: without it a backup cannot be opened.') }}</flux:text>
</div>
