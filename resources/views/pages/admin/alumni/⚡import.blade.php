<?php

use App\Models\Alumnus;
use App\Models\Site;
use App\Support\AlumniImport;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new #[Title('Bulk add alumni')] class extends Component {
    use WithFileUploads;

    public string $text = '';

    /** @var TemporaryUploadedFile|null */
    public $file = null;

    /**
     * Checked rows from the last preview, see AlumniImport::check().
     *
     * @var list<array{line: int, name: string, year: int|null, city: string, status: string, message: string}>
     */
    public array $rows = [];

    public bool $publish = true;

    public function mount(): void
    {
        $this->authorize('alumni.create');
    }

    /**
     * The current site when it has the alumni module and the user manages it.
     */
    #[Computed]
    public function site(): ?Site
    {
        $site = current_site();

        return $site?->hasModule('alumni') && Auth::user()->canAccessSite($site) ? $site : null;
    }

    #[Computed]
    public function canPublish(): bool
    {
        return Auth::user()->can('alumni.publish');
    }

    /**
     * @return array{ok: int, duplicate: int, error: int}
     */
    #[Computed]
    public function counts(): array
    {
        $counts = ['ok' => 0, 'duplicate' => 0, 'error' => 0];
        foreach ($this->rows as $row) {
            $counts[$row['status']] = ($counts[$row['status']] ?? 0) + 1;
        }

        return $counts;
    }

    public function preview(AlumniImport $import): void
    {
        abort_if($this->site === null, 403);

        $this->validate([
            'text' => ['nullable', 'string', 'max:500000', 'required_without:file'],
            'file' => ['nullable', 'file', 'mimes:csv,txt,xlsx', 'max:5120', 'required_without:text'],
        ], [
            'text.required_without' => __('Paste rows or choose a file.'),
            'file.required_without' => __('Paste rows or choose a file.'),
        ]);

        $cells = $this->file ? $import->fromFile($this->file) : $import->fromText($this->text);

        if (count($cells) > AlumniImport::MAX_ROWS + 1) {
            $this->addError('text', __('At most :max rows at a time.', ['max' => AlumniImport::MAX_ROWS]));
            $this->rows = [];

            return;
        }

        $this->rows = $import->check($cells);
        unset($this->counts);

        if ($this->rows === []) {
            $this->addError('text', __('No rows found.'));
        }
    }

    public function import(AlumniImport $import): void
    {
        $this->authorize('alumni.create');
        $site = $this->site;
        abort_if($site === null, 403);

        // Check again on the server: the preview state comes from the browser.
        $rows = $import->check(array_map(fn (array $row) => [$row['name'], (string) $row['year'], $row['city']], $this->rows));
        $valid = array_values(array_filter($rows, fn (array $row) => $row['status'] === 'ok'));
        $publish = $this->publish && $this->canPublish;

        DB::transaction(function () use ($valid, $site, $publish) {
            foreach ($valid as $row) {
                $alumnus = new Alumnus(['site_id' => $site->id, 'name' => $row['name'], 'graduation_year' => $row['year'], 'city' => $row['city'] ?: null]);
                $alumnus->forceFill(['is_published' => $publish])->save();
            }
        });

        $skipped = count($rows) - count($valid);
        $this->reset('text', 'file', 'rows');
        unset($this->counts);

        Flux::toast(variant: 'success', text: trans_choice(':count alumnus added.|:count alumni added.', count($valid)).($skipped ? ' '.trans_choice(':count row skipped.|:count rows skipped.', $skipped) : ''));
    }

    public function clear(): void
    {
        $this->reset('text', 'file', 'rows');
        $this->resetValidation();
        unset($this->counts);
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <div>
        <flux:link :href="route('admin.resources', 'alumni')" wire:navigate class="text-sm">← {{ __('Alumni') }}</flux:link>
        <flux:heading size="xl" class="mt-1">{{ __('Bulk add alumni') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Paste rows copied from Excel, or upload a CSV or Excel file. Each row: name, graduation year, city.') }}</flux:text>
    </div>

    @if (! current_site())
        <x-admin.needs-site />
    @elseif (! $this->site)
        <flux:callout icon="information-circle" color="amber" :text="__('This site does not have an alumni list, or you do not manage it.')" />
    @elseif ($rows === [])
        <flux:card class="max-w-3xl space-y-5">
            <flux:textarea wire:model="text" :label="__('Paste rows')" rows="10" class="font-mono text-sm" placeholder="Ahmad Fauzi&#9;2018&#9;Kendal&#10;Siti Nurhaliza; 2019; Semarang" :description="__('One alumnus per line. Columns separated by tabs (copied from Excel), semicolons or commas. A header row (Nama, Tahun, Kota) is skipped.')" />
            <flux:separator :text="__('or')" />
            <flux:field>
                <flux:label>{{ __('Upload a file') }}</flux:label>
                <flux:input type="file" wire:model="file" accept=".csv,.txt,.xlsx" />
                <flux:description>
                    {{ __('CSV or Excel (.xlsx), first sheet, up to :max rows.', ['max' => number_format(App\Support\AlumniImport::MAX_ROWS)]) }}
                    <flux:link :href="route('admin.alumni.template')">{{ __('Download the template') }}</flux:link>
                </flux:description>
                <flux:error name="file" />
            </flux:field>
            <flux:error name="text" />
            <div class="flex justify-end">
                <flux:button variant="primary" icon="eye" wire:click="preview">{{ __('Check rows') }}</flux:button>
            </div>
        </flux:card>
    @else
        <div class="flex flex-wrap items-center gap-2">
            <flux:badge color="emerald">{{ trans_choice(':count row ready|:count rows ready', $this->counts['ok']) }}</flux:badge>
            @if ($this->counts['duplicate'])
                <flux:badge color="amber">{{ trans_choice(':count duplicate|:count duplicates', $this->counts['duplicate']) }}</flux:badge>
            @endif
            @if ($this->counts['error'])
                <flux:badge color="red">{{ trans_choice(':count row with errors|:count rows with errors', $this->counts['error']) }}</flux:badge>
            @endif
            <flux:text size="sm">{{ __('Duplicates and rows with errors are skipped.') }}</flux:text>
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Line') }}</flux:table.column>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Graduation year') }}</flux:table.column>
                <flux:table.column>{{ __('City') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach (array_slice($rows, 0, 300) as $row)
                    <flux:table.row :key="'row-'.$row['line']">
                        <flux:table.cell class="font-mono text-xs text-zinc-500">{{ $row['line'] }}</flux:table.cell>
                        <flux:table.cell class="font-medium">{{ $row['name'] ?: '–' }}</flux:table.cell>
                        <flux:table.cell>{{ $row['year'] ?? '–' }}</flux:table.cell>
                        <flux:table.cell>{{ $row['city'] ?: '–' }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($row['status'] === 'ok')
                                <flux:badge size="sm" color="emerald">{{ __('Ready') }}</flux:badge>
                            @else
                                <flux:badge size="sm" :color="$row['status'] === 'duplicate' ? 'amber' : 'red'">{{ $row['message'] }}</flux:badge>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
        @if (count($rows) > 300)
            <flux:text size="sm">{{ __('Showing the first 300 of :count rows.', ['count' => count($rows)]) }}</flux:text>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-4">
            @if ($this->canPublish)
                <flux:checkbox wire:model="publish" :label="__('Publish now (show on the website)')" />
            @else
                <flux:text size="sm">{{ __('New alumni wait unpublished until an editor publishes them.') }}</flux:text>
            @endif
            <div class="flex gap-2">
                <flux:button wire:click="clear">{{ __('Start over') }}</flux:button>
                <flux:button variant="primary" icon="plus" wire:click="import" :disabled="$this->counts['ok'] === 0">
                    {{ trans_choice('Add :count alumnus|Add :count alumni', $this->counts['ok']) }}
                </flux:button>
            </div>
        </div>
    @endif
</div>
