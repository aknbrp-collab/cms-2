<?php

use App\Livewire\ReferenceList;
use App\Models\EducationUnit;
use App\Models\EducationUnitSubject;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;

new #[Title('Curriculum')] class extends ReferenceList {
    #[Locked]
    public string $unitId = '';

    public ?string $editingId = null;

    public string $grade = '';

    public string $name = '';

    public string $books = '';

    public function mount(EducationUnit $unit): void
    {
        $this->authorize('education_units.view');
        $this->unitId = $unit->id;
    }

    protected function module(): string
    {
        return 'education_units';
    }

    protected function findItem(string $id): Model
    {
        return EducationUnit::query()->with('site')->findOrFail($id);
    }

    #[Computed]
    public function unit(): EducationUnit
    {
        return EducationUnit::query()->with('site')->findOrFail($this->unitId);
    }

    /**
     * Subjects grouped by class/level, in the order the levels were added.
     *
     * @return \Illuminate\Support\Collection<string, Collection<int, EducationUnitSubject>>
     */
    #[Computed]
    public function grades(): \Illuminate\Support\Collection
    {
        return $this->unit->curriculum();
    }

    public function edit(string $id): void
    {
        $subject = $this->unit->subjects()->findOrFail($id);
        $this->fill([
            'editingId' => $subject->id,
            'grade' => $subject->grade,
            'name' => $subject->name,
            'books' => (string) $subject->books,
        ]);
        $this->resetValidation();

        Flux::modal('subject-form')->show();
    }

    public function create(?string $grade = null): void
    {
        $this->reset('editingId', 'name', 'books');
        $this->grade = $grade ?? $this->grade;
        $this->resetValidation();

        Flux::modal('subject-form')->show();
    }

    public function save(): void
    {
        $this->authorizeItem($this->unit, 'update');

        $validated = $this->validate([
            'grade' => ['required', 'string', 'max:60'],
            'name' => ['required', 'string', 'max:255'],
            'books' => ['nullable', 'string', 'max:255'],
        ]);

        $attributes = [...$validated, 'grade' => trim($validated['grade']), 'books' => $validated['books'] ?: null];

        if ($this->editingId) {
            $this->unit->subjects()->findOrFail($this->editingId)->update($attributes);
        } else {
            $this->unit->subjects()->create([
                ...$attributes,
                'sort_order' => (int) $this->unit->subjects()->max('sort_order') + 1,
            ]);
        }

        unset($this->unit, $this->grades);
        Flux::modal('subject-form')->close();
        Flux::toast(variant: 'success', text: __('Subject saved.'));
    }

    public function delete(string $id): void
    {
        $this->authorizeItem($this->unit, 'update');
        $this->unit->subjects()->whereKey($id)->delete();

        unset($this->unit, $this->grades);
        Flux::toast(text: __('Subject removed.'));
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:link :href="route('admin.resources', 'education_units')" wire:navigate class="text-sm">← {{ __('Education units') }}</flux:link>
            <flux:heading size="xl" class="mt-1">{{ __('Curriculum') }}: {{ $this->unit->name }}</flux:heading>
            <flux:text class="mt-1">{{ trans_choice(':count subject|:count subjects', $this->grades->flatten()->count()) }}</flux:text>
        </div>
        @can('education_units.update')
            <flux:button variant="primary" icon="plus" wire:click="create">{{ __('Add subject') }}</flux:button>
        @endcan
    </div>

    @forelse ($this->grades as $grade => $subjects)
        <flux:card wire:key="grade-{{ md5($grade) }}" class="space-y-3">
            <div class="flex items-center justify-between">
                <flux:heading>{{ $grade }}</flux:heading>
                @can('education_units.update')
                    <flux:button size="sm" variant="ghost" icon="plus" wire:click="create({{ \Illuminate\Support\Js::from($grade) }})">{{ __('Add') }}</flux:button>
                @endcan
            </div>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Lesson') }}</flux:table.column>
                    <flux:table.column>{{ __('Books (kitab)') }}</flux:table.column>
                    <flux:table.column />
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($subjects as $subject)
                        <flux:table.row :key="$subject->id">
                            <flux:table.cell class="font-medium">{{ $subject->name }}</flux:table.cell>
                            <flux:table.cell class="text-zinc-500">{{ $subject->books }}</flux:table.cell>
                            <flux:table.cell align="end">
                                @can('education_units.update')
                                    <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit('{{ $subject->id }}')" />
                                    <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete('{{ $subject->id }}')" wire:confirm="{{ __('Remove this subject?') }}" />
                                @endcan
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </flux:card>
    @empty
        <flux:text>{{ __('No subjects yet.') }}</flux:text>
    @endforelse

    <flux:modal name="subject-form" class="md:w-[32rem]">
        <form wire:submit="save" class="space-y-4">
            <flux:heading size="lg">{{ $editingId ? __('Edit subject') : __('Add subject') }}</flux:heading>
            <flux:input wire:model="grade" :label="__('Class / level')" :description="__('e.g. Kelas 1 or Juz 1–5. Subjects with the same class are grouped.')" required />
            <flux:input wire:model="name" :label="__('Lesson')" required />
            <flux:input wire:model="books" :label="__('Books (kitab)')" />
            <div class="flex justify-end"><flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button></div>
        </form>
    </flux:modal>
</div>
