<?php

use App\Livewire\ReferenceList;
use App\Models\AdmissionPeriod;
use App\Models\AdmissionRequirement;
use App\Support\RichText;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;

new #[Title('Admission period')] class extends ReferenceList {
    #[Locked]
    public ?string $periodId = null;

    public string $title = '';

    public string $academicYear = '';

    public string $opensOn = '';

    public string $closesOn = '';

    public string $summary = '';

    public string $requirements = '';

    public string $fees = '';

    /** @var list<array{title: string, description: string}> */
    public array $steps = [];

    /** One program per line. */
    public string $programs = '';

    public ?string $brochureId = null;

    public bool $acceptsOnlineRegistration = false;

    /**
     * Documents applicants upload after the form.
     *
     * @var list<array{id: string|null, name: string, description: string, is_required: bool, file_types: list<string>, max_mb: int|float|string}>
     */
    public array $documentRows = [];

    public function mount(?AdmissionPeriod $period = null): void
    {
        if ($period?->exists) {
            $this->authorizeItem($period, 'update');
            $this->periodId = $period->id;
            $this->fill([
                'title' => $period->title,
                'academicYear' => $period->academic_year,
                'opensOn' => $period->opens_on->toDateString(),
                'closesOn' => $period->closes_on->toDateString(),
                'summary' => (string) $period->summary,
                'requirements' => (string) $period->requirements,
                'fees' => (string) $period->fees,
                'steps' => $period->steps ?? [],
                'programs' => implode("\n", $period->programs ?? []),
                'brochureId' => $period->brochure_id,
                'acceptsOnlineRegistration' => $period->accepts_online_registration,
            ]);
            $this->documentRows = $period->documentRequirements->map(fn (AdmissionRequirement $requirement) => [
                'id' => $requirement->id,
                'name' => $requirement->name,
                'description' => (string) $requirement->description,
                'is_required' => $requirement->is_required,
                'file_types' => $requirement->file_types,
                'max_mb' => round($requirement->max_size_kb / 1024, 1),
            ])->all();
        } else {
            $this->authorizeCreate();
            $this->opensOn = today()->toDateString();
            $this->closesOn = today()->addMonths(2)->toDateString();
            $this->steps = [['title' => '', 'description' => '']];
        }
    }

    protected function module(): string
    {
        return 'admissions';
    }

    protected function findItem(string $id): AdmissionPeriod
    {
        return AdmissionPeriod::query()->with('site')->findOrFail($id);
    }

    public function addStep(): void
    {
        $this->steps[] = ['title' => '', 'description' => ''];
    }

    public function removeStep(int $index): void
    {
        unset($this->steps[$index]);
        $this->steps = array_values($this->steps);
    }

    public function addDocument(): void
    {
        $this->documentRows[] = [
            'id' => null, 'name' => '', 'description' => '', 'is_required' => true,
            'file_types' => ['pdf', 'jpg', 'png'], 'max_mb' => AdmissionRequirement::DEFAULT_MAX_SIZE_KB / 1024,
        ];
    }

    public function removeDocument(int $index): void
    {
        unset($this->documentRows[$index]);
        $this->documentRows = array_values($this->documentRows);
    }

    public function moveDocument(int $index, int $direction): void
    {
        if (isset($this->documentRows[$index], $this->documentRows[$index + $direction])) {
            [$this->documentRows[$index], $this->documentRows[$index + $direction]] = [$this->documentRows[$index + $direction], $this->documentRows[$index]];
        }
    }

    public function save(): void
    {
        if ($this->periodId) {
            $period = $this->findItem($this->periodId);
            $this->authorizeItem($period, 'update');
        } else {
            $period = new AdmissionPeriod(['site_id' => $this->authorizeCreate()->id]);
        }

        $this->steps = array_values(array_filter($this->steps, fn (array $step) => trim($step['title'] ?? '') !== ''));

        $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'academicYear' => ['required', 'string', 'max:20'],
            'opensOn' => ['required', 'date'],
            'closesOn' => ['required', 'date', 'after_or_equal:opensOn'],
            'summary' => ['nullable', 'string', 'max:2000'],
            'requirements' => ['nullable', 'string', 'max:100000'],
            'fees' => ['nullable', 'string', 'max:100000'],
            'steps' => ['array', 'max:12'],
            'steps.*.title' => ['required', 'string', 'max:100'],
            'steps.*.description' => ['nullable', 'string', 'max:500'],
            'programs' => ['nullable', 'string', 'max:2000'],
            'brochureId' => ['nullable', 'exists:media_assets,id'],
            'acceptsOnlineRegistration' => ['boolean'],
            'documentRows' => ['array', 'max:20'],
            'documentRows.*.name' => ['required', 'string', 'max:150'],
            'documentRows.*.description' => ['nullable', 'string', 'max:500'],
            'documentRows.*.is_required' => ['boolean'],
            'documentRows.*.file_types' => ['required', 'array', 'min:1'],
            'documentRows.*.file_types.*' => [Rule::in(array_keys(AdmissionRequirement::FILE_TYPES))],
            'documentRows.*.max_mb' => ['required', 'numeric', 'min:0.5', 'max:'.(AdmissionRequirement::MAX_SIZE_KB / 1024)],
        ], [], [
            'documentRows.*.name' => __('document name'),
            'documentRows.*.file_types' => __('file types'),
            'documentRows.*.max_mb' => __('maximum size'),
        ]);

        $period->fill([
            'title' => $this->title,
            'academic_year' => $this->academicYear,
            'opens_on' => $this->opensOn,
            'closes_on' => $this->closesOn,
            'summary' => $this->summary ?: null,
            'requirements' => RichText::clean($this->requirements) ?: null,
            'fees' => RichText::clean($this->fees) ?: null,
            'steps' => array_map(fn (array $step) => ['title' => $step['title'], 'description' => (string) ($step['description'] ?? '')], $this->steps),
            'programs' => array_values(array_filter(array_map('trim', explode("\n", $this->programs)))),
            'brochure_id' => $this->brochureId,
            'accepts_online_registration' => $this->acceptsOnlineRegistration,
        ]);

        DB::transaction(function () use ($period) {
            $period->save();
            $this->syncDocumentRequirements($period);
        });

        if ($this->periodId === null) {
            Flux::toast(variant: 'success', text: __('Period created. Publish it from the list when ready.'));
            $this->redirectRoute('admin.admissions.edit', $period, navigate: true);

            return;
        }

        Flux::toast(variant: 'success', text: __('Saved.'));
    }

    /**
     * Create, update, reorder and delete the period's document requirements.
     * Requirements that already have uploads cannot be deleted.
     */
    protected function syncDocumentRequirements(AdmissionPeriod $period): void
    {
        $keptIds = array_values(array_filter(array_column($this->documentRows, 'id')));
        $removed = $period->documentRequirements()->whereNotIn('id', $keptIds)->withCount('documents')->get();

        if ($inUse = $removed->firstWhere('documents_count', '>', 0)) {
            throw ValidationException::withMessages([
                'documentRows' => __('":name" already has uploaded files and cannot be removed.', ['name' => $inUse->name]),
            ]);
        }

        $removed->each->delete();

        foreach ($this->documentRows as $position => $row) {
            $requirement = $row['id']
                ? $period->documentRequirements()->findOrFail($row['id'])
                : new AdmissionRequirement(['admission_period_id' => $period->id]);

            $requirement->fill([
                'name' => $row['name'],
                'description' => $row['description'] ?: null,
                'is_required' => (bool) $row['is_required'],
                'file_types' => array_values(array_intersect(array_keys(AdmissionRequirement::FILE_TYPES), $row['file_types'])),
                'max_size_kb' => (int) round((float) $row['max_mb'] * 1024),
                'sort_order' => $position,
            ])->save();

            $this->documentRows[$position]['id'] = $requirement->id;
        }
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:link :href="route('admin.admissions.index')" wire:navigate class="text-sm">← {{ __('Admissions') }}</flux:link>
            <flux:heading size="xl" class="mt-1">{{ $periodId ? $title : __('New admission period') }}</flux:heading>
        </div>
        <flux:button variant="primary" wire:click="save">{{ __('Save') }}</flux:button>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="min-w-0 space-y-6 lg:col-span-2">
            <flux:card class="space-y-5">
                <div class="grid gap-5 sm:grid-cols-2">
                    <flux:input wire:model="title" :label="__('Title')" :placeholder="__('e.g. Gelombang II')" required />
                    <flux:input wire:model="academicYear" :label="__('Academic year')" placeholder="2026/2027" required />
                </div>
                <flux:textarea wire:model="summary" :label="__('Summary')" rows="3" />
                <x-admin.rich-text model="requirements" :label="__('Requirements')" />
                <x-admin.rich-text model="fees" :label="__('Fees & scholarships')" />
            </flux:card>

            <flux:card class="space-y-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <flux:heading>{{ __('Documents to upload') }}</flux:heading>
                        <flux:text size="sm">{{ __('After the form, applicants upload one file for each document, now or later with their registration number.') }}</flux:text>
                    </div>
                    <flux:button size="sm" icon="plus" wire:click="addDocument">{{ __('Add document') }}</flux:button>
                </div>
                <flux:error name="documentRows" />
                @forelse ($documentRows as $index => $row)
                    <div wire:key="document-{{ $row['id'] ?? 'new-'.$index }}" class="space-y-3 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <div class="flex items-start gap-2">
                            <div class="grid min-w-0 flex-1 gap-3 sm:grid-cols-2">
                                <flux:input wire:model="documentRows.{{ $index }}.name" :label="__('Document')" :placeholder="__('e.g. Kartu Keluarga')" />
                                <flux:input wire:model="documentRows.{{ $index }}.description" :label="__('Instructions (optional)')" :placeholder="__('e.g. Scan of the original, all pages')" />
                            </div>
                            <div class="flex shrink-0 gap-1 pt-6">
                                <flux:button size="xs" variant="ghost" icon="arrow-up" wire:click="moveDocument({{ $index }}, -1)" :disabled="$loop->first" />
                                <flux:button size="xs" variant="ghost" icon="arrow-down" wire:click="moveDocument({{ $index }}, 1)" :disabled="$loop->last" />
                                <flux:button size="xs" variant="ghost" icon="trash" wire:click="removeDocument({{ $index }})" />
                            </div>
                        </div>
                        <div class="flex flex-wrap items-end gap-x-6 gap-y-3">
                            <fieldset>
                                <legend class="mb-2 text-sm font-medium text-zinc-800 dark:text-white">{{ __('File types') }}</legend>
                                <div class="flex flex-wrap gap-4 pb-2">
                                    @foreach (AdmissionRequirement::FILE_TYPES as $key => $type)
                                        <flux:checkbox wire:model="documentRows.{{ $index }}.file_types" :value="$key" :label="$type['label']" />
                                    @endforeach
                                </div>
                            </fieldset>
                            <flux:input type="number" step="0.5" min="0.5" max="12" wire:model="documentRows.{{ $index }}.max_mb" :label="__('Max. size (MB)')" class="max-w-32" />
                            <flux:checkbox wire:model="documentRows.{{ $index }}.is_required" :label="__('Required')" class="pb-2" />
                        </div>
                        <flux:error name="documentRows.{{ $index }}.name" />
                        <flux:error name="documentRows.{{ $index }}.file_types" />
                        <flux:error name="documentRows.{{ $index }}.max_mb" />
                    </div>
                @empty
                    <flux:text size="sm">{{ __('No documents yet. Without them, registration ends after the form.') }}</flux:text>
                @endforelse
            </flux:card>

            <flux:card class="space-y-4">
                <div class="flex items-center justify-between">
                    <flux:heading>{{ __('Steps') }}</flux:heading>
                    <flux:button size="sm" icon="plus" wire:click="addStep">{{ __('Add step') }}</flux:button>
                </div>
                @foreach ($steps as $index => $step)
                    <div wire:key="step-{{ $index }}" class="grid gap-3 sm:grid-cols-[2rem_1fr_2fr_auto] sm:items-start">
                        <span class="pt-2 text-sm font-semibold text-zinc-500">{{ $index + 1 }}.</span>
                        <flux:input wire:model="steps.{{ $index }}.title" :placeholder="__('Step')" />
                        <flux:input wire:model="steps.{{ $index }}.description" :placeholder="__('Description')" />
                        <flux:button size="sm" variant="ghost" icon="trash" wire:click="removeStep({{ $index }})" />
                    </div>
                @endforeach
            </flux:card>
        </div>

        <div class="space-y-6">
            <flux:card class="space-y-5">
                <flux:input type="date" wire:model="opensOn" :label="__('Opens')" required />
                <flux:input type="date" wire:model="closesOn" :label="__('Closes')" required />
                <flux:switch wire:model="acceptsOnlineRegistration" :label="__('Accept online registration')" :description="__('Shows the registration form on the website while the period is open.')" />
                <flux:textarea wire:model="programs" :label="__('Programs to choose from')" :description="__('One per line. Leave empty to hide the choice.')" rows="3" />
                <livewire:admin.media-picker wire:model="brochureId" kind="document" :label="__('Brochure (PDF)')" />
            </flux:card>
        </div>
    </div>
</div>
