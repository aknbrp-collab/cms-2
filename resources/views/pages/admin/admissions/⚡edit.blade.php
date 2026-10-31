<?php

use App\Livewire\ReferenceList;
use App\Models\AdmissionPeriod;
use App\Support\RichText;
use Flux\Flux;
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
        ])->save();

        if ($this->periodId === null) {
            Flux::toast(variant: 'success', text: __('Period created. Publish it from the list when ready.'));
            $this->redirectRoute('admin.admissions.edit', $period, navigate: true);

            return;
        }

        Flux::toast(variant: 'success', text: __('Saved.'));
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
