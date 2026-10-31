<?php

use App\Livewire\ReferenceList;
use App\Models\Course;
use App\Models\Program;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;

new #[Title('Curriculum')] class extends ReferenceList {
    #[Locked]
    public string $programId = '';

    public ?string $editingId = null;

    public int $semester = 1;

    public string $code = '';

    public string $name = '';

    public int $credits = 2;

    public string $books = '';

    public function mount(Program $program): void
    {
        $this->authorize('programs.view');
        $this->programId = $program->id;
    }

    protected function module(): string
    {
        return 'programs';
    }

    protected function findItem(string $id): Model
    {
        return Program::query()->with('site')->findOrFail($id);
    }

    #[Computed]
    public function program(): Program
    {
        return Program::query()->with('site')->findOrFail($this->programId);
    }

    /**
     * Courses grouped by semester.
     *
     * @return \Illuminate\Support\Collection<int, Collection<int, Course>>
     */
    #[Computed]
    public function semesters(): \Illuminate\Support\Collection
    {
        return $this->program->courses()->get()->groupBy('semester')->sortKeys();
    }

    public function edit(string $id): void
    {
        $course = $this->program->courses()->findOrFail($id);
        $this->fill([
            'editingId' => $course->id,
            'semester' => $course->semester,
            'code' => (string) $course->code,
            'name' => $course->name,
            'credits' => $course->credits,
            'books' => (string) $course->books,
        ]);

        Flux::modal('course-form')->show();
    }

    public function create(?int $semester = null): void
    {
        $this->reset('editingId', 'code', 'name', 'credits', 'books');
        $this->semester = $semester ?? $this->semester;
        $this->resetValidation();

        Flux::modal('course-form')->show();
    }

    public function save(): void
    {
        $this->authorizeItem($this->program, 'update');

        $validated = $this->validate([
            'semester' => ['required', 'integer', 'min:1', 'max:12'],
            'code' => ['nullable', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:255'],
            'credits' => ['required', 'integer', 'min:0', 'max:24'],
            'books' => ['nullable', 'string', 'max:255'],
        ]);

        $attributes = [...$validated, 'code' => $validated['code'] ?: null, 'books' => $validated['books'] ?: null];

        if ($this->editingId) {
            $this->program->courses()->findOrFail($this->editingId)->update($attributes);
        } else {
            $this->program->courses()->create([
                ...$attributes,
                'sort_order' => (int) $this->program->courses()->where('semester', $validated['semester'])->max('sort_order') + 1,
            ]);
        }

        unset($this->semesters);
        Flux::modal('course-form')->close();
        Flux::toast(variant: 'success', text: __('Course saved.'));
    }

    public function delete(string $id): void
    {
        $this->authorizeItem($this->program, 'update');
        $this->program->courses()->whereKey($id)->delete();

        unset($this->semesters);
        Flux::toast(text: __('Course removed.'));
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:link :href="route('admin.resources', 'programs')" wire:navigate class="text-sm">← {{ __('Programs') }}</flux:link>
            <flux:heading size="xl" class="mt-1">{{ __('Curriculum') }}: {{ $this->program->name }}</flux:heading>
            <flux:text class="mt-1">{{ $this->program->level->label() }} · {{ trans_choice(':count course|:count courses', $this->semesters->flatten()->count()) }} · {{ $this->semesters->flatten()->sum('credits') }} SKS</flux:text>
        </div>
        @can('programs.update')
            <flux:button variant="primary" icon="plus" wire:click="create">{{ __('Add course') }}</flux:button>
        @endcan
    </div>

    @forelse ($this->semesters as $semester => $courses)
        <flux:card wire:key="semester-{{ $semester }}" class="space-y-3">
            <div class="flex items-center justify-between">
                <flux:heading>{{ __('Semester :n', ['n' => $semester]) }} <span class="font-normal text-zinc-500">· {{ $courses->sum('credits') }} SKS</span></flux:heading>
                @can('programs.update')
                    <flux:button size="sm" variant="ghost" icon="plus" wire:click="create({{ $semester }})">{{ __('Add') }}</flux:button>
                @endcan
            </div>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Code') }}</flux:table.column>
                    <flux:table.column>{{ __('Course') }}</flux:table.column>
                    <flux:table.column>{{ __('Books') }}</flux:table.column>
                    <flux:table.column>SKS</flux:table.column>
                    <flux:table.column />
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($courses as $course)
                        <flux:table.row :key="$course->id">
                            <flux:table.cell class="font-mono text-xs">{{ $course->code }}</flux:table.cell>
                            <flux:table.cell class="font-medium">{{ $course->name }}</flux:table.cell>
                            <flux:table.cell class="text-zinc-500">{{ $course->books }}</flux:table.cell>
                            <flux:table.cell>{{ $course->credits }}</flux:table.cell>
                            <flux:table.cell align="end">
                                @can('programs.update')
                                    <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit('{{ $course->id }}')" />
                                    <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete('{{ $course->id }}')" wire:confirm="{{ __('Remove this course?') }}" />
                                @endcan
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </flux:card>
    @empty
        <flux:text>{{ __('No courses yet.') }}</flux:text>
    @endforelse

    <flux:modal name="course-form" class="md:w-[32rem]">
        <form wire:submit="save" class="space-y-4">
            <flux:heading size="lg">{{ $editingId ? __('Edit course') : __('Add course') }}</flux:heading>
            <div class="grid gap-4 sm:grid-cols-3">
                <flux:input wire:model="semester" type="number" min="1" max="12" :label="__('Semester')" required />
                <flux:input wire:model="code" :label="__('Code')" />
                <flux:input wire:model="credits" type="number" min="0" max="24" label="SKS" required />
            </div>
            <flux:input wire:model="name" :label="__('Course name')" required />
            <flux:input wire:model="books" :label="__('Books (kitab)')" />
            <div class="flex justify-end"><flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button></div>
        </form>
    </flux:modal>
</div>
