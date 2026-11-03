<?php

use App\Enums\DocumentStatus;
use App\Enums\RegistrationStatus;
use App\Models\AdmissionDocument;
use App\Models\AdmissionPeriod;
use App\Models\AdmissionRegistration;
use App\Services\AdmissionDocuments;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Registrations')] class extends Component {
    use WithPagination;

    #[Url]
    public string $period = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $search = '';

    /** '' (all), 'complete', 'incomplete' or 'needs_fix'. */
    #[Url]
    public string $documents = '';

    public ?string $fixingDocumentId = null;

    public string $fixNote = '';

    public ?string $viewingId = null;

    public string $newStatus = '';

    public string $adminNotes = '';

    public function mount(): void
    {
        $this->authorize('admissions.view');
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['period', 'status', 'search', 'documents'], true)) {
            $this->resetPage();
        }
    }

    /**
     * @return Collection<int, AdmissionPeriod>
     */
    #[Computed]
    public function periods(): Collection
    {
        return AdmissionPeriod::query()->latest('opens_on')->get(['id', 'title', 'academic_year', 'site_id']);
    }

    /**
     * @return LengthAwarePaginator<int, AdmissionRegistration>
     */
    #[Computed]
    public function registrations(): LengthAwarePaginator
    {
        return AdmissionRegistration::query()
            ->with(['period.documentRequirements', 'documents'])
            ->whereIn('site_id', Auth::user()->accessibleSites()->modelKeys())
            ->when($this->period !== '', fn ($query) => $query->where('admission_period_id', $this->period))
            ->when($this->documents === 'complete', fn ($query) => $query->documentsComplete())
            ->when($this->documents === 'incomplete', fn ($query) => $query->documentsIncomplete())
            ->when($this->documents === 'needs_fix', fn ($query) => $query->documentsNeedFix())
            ->when(RegistrationStatus::tryFrom($this->status), fn ($query, RegistrationStatus $status) => $query->where('status', $status->value))
            ->when($this->search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->whereLike('full_name', "%{$this->search}%")
                ->orWhereLike('registration_number', "%{$this->search}%")))
            ->latest()
            ->paginate(25);
    }

    #[Computed]
    public function viewing(): ?AdmissionRegistration
    {
        return $this->viewingId ? $this->find($this->viewingId) : null;
    }

    public function show(string $id): void
    {
        $registration = $this->find($id);
        $this->viewingId = $registration->id;
        $this->newStatus = $registration->status->value;
        $this->adminNotes = (string) $registration->admin_notes;
        $this->reset('fixingDocumentId', 'fixNote');

        Flux::modal('registration-detail')->show();
    }

    public function acceptDocument(string $id, AdmissionDocuments $documents): void
    {
        $this->authorize('admissions.update');
        $documents->review($this->findDocument($id), DocumentStatus::Accepted, null, Auth::user());
        $this->reset('fixingDocumentId', 'fixNote');
        unset($this->viewing, $this->registrations);

        Flux::toast(variant: 'success', text: __('Document accepted.'));
    }

    public function startFix(string $id): void
    {
        $this->authorize('admissions.update');
        $this->fixingDocumentId = $this->findDocument($id)->id;
        $this->fixNote = '';
    }

    public function requestFix(AdmissionDocuments $documents): void
    {
        $this->authorize('admissions.update');
        $document = $this->findDocument((string) $this->fixingDocumentId);
        $this->validate(['fixNote' => ['required', 'string', 'max:1000']], [], ['fixNote' => __('note')]);

        $documents->review($document, DocumentStatus::NeedsFix, $this->fixNote, Auth::user());
        $this->reset('fixingDocumentId', 'fixNote');
        unset($this->viewing, $this->registrations);

        Flux::toast(variant: 'success', text: filled($document->registration->email)
            ? __('Marked as needing a fix. The applicant was emailed.')
            : __('Marked as needing a fix. No email was given: contact the parent by phone.'));
    }

    public function update(): void
    {
        $this->authorize('admissions.update');
        $registration = $this->find((string) $this->viewingId);

        $this->validate([
            'newStatus' => ['required', Rule::enum(RegistrationStatus::class)],
            'adminNotes' => ['nullable', 'string', 'max:5000'],
        ]);

        $registration->forceFill(['status' => $this->newStatus, 'admin_notes' => $this->adminNotes ?: null])->save();

        Flux::modal('registration-detail')->close();
        Flux::toast(variant: 'success', text: __('Registration updated.'));
    }

    protected function findDocument(string $id): AdmissionDocument
    {
        return $this->viewing?->documents->firstWhere('id', $id) ?? abort(404);
    }

    protected function find(string $id): AdmissionRegistration
    {
        return AdmissionRegistration::query()
            ->with(['period.documentRequirements', 'documents.media'])
            ->whereIn('site_id', Auth::user()->accessibleSites()->modelKeys())
            ->findOrFail($id);
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:link :href="route('admin.admissions.index')" wire:navigate class="text-sm">← {{ __('Admissions') }}</flux:link>
            <flux:heading size="xl" class="mt-1">{{ __('Registrations') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Online admission forms. Contains personal data: handle with care.') }}</flux:text>
        </div>
        <flux:button icon="arrow-down-tray" :href="route('admin.admissions.export', array_filter(['period' => $period, 'status' => $status]))">{{ __('Export CSV') }}</flux:button>
    </div>

    <div class="flex flex-wrap gap-3">
        <flux:input wire:model.live.debounce.300ms="search" :placeholder="__('Name or number')" icon="magnifying-glass" class="max-w-xs" />
        <flux:select wire:model.live="period" class="max-w-64">
            <option value="">{{ __('All periods') }}</option>
            @foreach ($this->periods as $item)
                <option value="{{ $item->id }}">{{ $item->title }} ({{ $item->academic_year }})</option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="status" class="max-w-48">
            <option value="">{{ __('All statuses') }}</option>
            @foreach (RegistrationStatus::cases() as $case)
                <option value="{{ $case->value }}">{{ $case->label() }}</option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="documents" class="max-w-52">
            <option value="">{{ __('All documents') }}</option>
            <option value="complete">{{ __('Documents complete') }}</option>
            <option value="incomplete">{{ __('Documents missing') }}</option>
            <option value="needs_fix">{{ __('Documents to fix') }}</option>
        </flux:select>
    </div>

    <flux:table :paginate="$this->registrations">
        <flux:table.columns>
            <flux:table.column>{{ __('Number') }}</flux:table.column>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Period') }}</flux:table.column>
            <flux:table.column>{{ __('Parent phone') }}</flux:table.column>
            <flux:table.column>{{ __('Documents') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column>{{ __('Submitted') }}</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->registrations as $registration)
                <flux:table.row :key="$registration->id">
                    <flux:table.cell class="font-mono text-xs">
                        <button type="button" wire:click="show('{{ $registration->id }}')" class="hover:underline">{{ $registration->registration_number }}</button>
                    </flux:table.cell>
                    <flux:table.cell class="font-medium">{{ $registration->full_name }}</flux:table.cell>
                    <flux:table.cell>{{ $registration->period->title }}</flux:table.cell>
                    <flux:table.cell>{{ $registration->parent_phone }}</flux:table.cell>
                    <flux:table.cell>
                        @include('pages.admin.admissions.partials.document-summary', ['summary' => $registration->documentSummary()])
                    </flux:table.cell>
                    <flux:table.cell><flux:badge size="sm" :color="$registration->status->color()">{{ $registration->status->label() }}</flux:badge></flux:table.cell>
                    <flux:table.cell class="whitespace-nowrap">{{ $registration->created_at?->translatedFormat('d M Y H:i') }}</flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7">{{ __('No registrations found.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="registration-detail" class="w-full md:w-[44rem]">
        @if ($registration = $this->viewing)
            <div class="space-y-5">
                <div>
                    <flux:heading size="lg">{{ $registration->full_name }}</flux:heading>
                    <flux:text class="font-mono">{{ $registration->registration_number }} · {{ $registration->period->title }}</flux:text>
                </div>

                <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                    @foreach ([
                        __('Gender') => $registration->gender === 'L' ? __('Male') : __('Female'),
                        __('Place, date of birth') => $registration->birth_place.', '.$registration->birth_date->translatedFormat('d F Y'),
                        __('NISN') => $registration->nisn ?: '–',
                        __('Previous school') => $registration->previous_school,
                        __('Program') => $registration->program ?: '–',
                        __('Parent / guardian') => $registration->parent_name,
                        __('Parent phone') => $registration->parent_phone,
                        __('Email') => $registration->email ?: '–',
                    ] as $label => $value)
                        <div>
                            <dt class="text-zinc-500">{{ $label }}</dt>
                            <dd class="font-medium">{{ $value }}</dd>
                        </div>
                    @endforeach
                    <div class="col-span-2">
                        <dt class="text-zinc-500">{{ __('Address') }}</dt>
                        <dd class="font-medium">{{ $registration->address }}</dd>
                    </div>
                </dl>

                @if ($registration->period->documentRequirements->isNotEmpty())
                    @include('pages.admin.admissions.partials.registration-documents')
                @endif

                @can('admissions.update')
                    <form wire:submit="update" class="space-y-4 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                        <flux:select wire:model="newStatus" :label="__('Status')">
                            @foreach (RegistrationStatus::cases() as $case)
                                <option value="{{ $case->value }}">{{ $case->label() }}</option>
                            @endforeach
                        </flux:select>
                        <flux:textarea wire:model="adminNotes" :label="__('Internal notes')" rows="3" />
                        <div class="flex justify-end"><flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button></div>
                    </form>
                @endcan
            </div>
        @endif
    </flux:modal>
</div>
