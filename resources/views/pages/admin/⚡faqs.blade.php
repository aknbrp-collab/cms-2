<?php

use App\Livewire\ReferenceList;
use App\Models\Faq;
use App\Support\RichText;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;

new #[Title('FAQ')] class extends ReferenceList {
    public ?string $editingId = null;

    public string $group = '';

    public string $question = '';

    public string $answer = '';

    public int $sortOrder = 0;

    /** Bumped on every form reset so the rich text editor re-mounts with fresh content. */
    public int $formVersion = 0;

    public function mount(): void
    {
        $this->authorize('faqs.view');
    }

    protected function module(): string
    {
        return 'faqs';
    }

    protected function findItem(string $id): Faq
    {
        return Faq::query()->with('site')->findOrFail($id);
    }

    /**
     * @return Collection<int, Faq>
     */
    #[Computed]
    public function faqs(): Collection
    {
        return Faq::query()->with('site')->orderBy('group')->orderBy('sort_order')->get();
    }

    public function create(): void
    {
        $this->authorizeCreate();
        $this->resetForm();
        Flux::modal('faq-form')->show();
    }

    public function edit(string $id): void
    {
        $faq = $this->findItem($id);
        $this->authorizeItem($faq, 'update');

        $this->resetForm();
        $this->fill([
            'editingId' => $faq->id,
            'group' => (string) $faq->group,
            'question' => $faq->question,
            'answer' => $faq->answer,
            'sortOrder' => $faq->sort_order,
        ]);

        Flux::modal('faq-form')->show();
    }

    public function save(): void
    {
        if ($this->editingId) {
            $faq = $this->findItem($this->editingId);
            $this->authorizeItem($faq, 'update');
        } else {
            $faq = new Faq(['site_id' => $this->authorizeCreate()->id]);
        }

        $this->validate([
            'group' => ['nullable', 'string', 'max:60'],
            'question' => ['required', 'string', 'max:255'],
            'answer' => ['required', 'string', 'max:20000'],
            'sortOrder' => ['integer', 'min:0', 'max:9999'],
        ]);

        $faq->fill([
            'group' => $this->group ?: null,
            'question' => $this->question,
            'answer' => RichText::clean($this->answer),
            'sort_order' => $this->sortOrder,
        ])->save();

        Flux::modal('faq-form')->close();
        Flux::toast(variant: 'success', text: __('Saved.'));
        $this->resetForm();
    }

    public function delete(string $id): void
    {
        $faq = $this->findItem($id);
        $this->authorizeItem($faq, 'delete');
        $faq->delete();

        Flux::toast(text: __('Deleted.'));
    }

    protected function resetForm(): void
    {
        $this->reset('editingId', 'group', 'question', 'answer', 'sortOrder');
        $this->resetValidation();
        $this->formVersion++;
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('FAQ') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Frequently asked questions, grouped by topic (e.g. Pendaftaran, Biaya).') }}</flux:text>
        </div>
        @if (current_site())
            @can('faqs.create')
                <flux:button variant="primary" icon="plus" wire:click="create">{{ __('New question') }}</flux:button>
            @endcan
        @endif
    </div>

    @if (! current_site())
        <x-admin.needs-site />
    @endif

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Question') }}</flux:table.column>
            <flux:table.column>{{ __('Group') }}</flux:table.column>
            @unless (current_site())
                <flux:table.column>{{ __('Site') }}</flux:table.column>
            @endunless
            <flux:table.column>{{ __('Shown on website') }}</flux:table.column>
            <flux:table.column />
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->faqs as $faq)
                <flux:table.row :key="$faq->id">
                    <flux:table.cell class="max-w-md font-medium">{{ $faq->question }}</flux:table.cell>
                    <flux:table.cell>{{ $faq->group }}</flux:table.cell>
                    @unless (current_site())
                        <flux:table.cell>{{ $faq->site->name }}</flux:table.cell>
                    @endunless
                    <flux:table.cell>
                        @can('faqs.publish')
                            <flux:switch :checked="$faq->is_published" wire:click="togglePublished('{{ $faq->id }}')" />
                        @else
                            <flux:badge size="sm" :color="$faq->is_published ? 'emerald' : 'zinc'">{{ $faq->is_published ? __('Yes') : __('No') }}</flux:badge>
                        @endcan
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        @can('faqs.update')
                            <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit('{{ $faq->id }}')" />
                        @endcan
                        @can('faqs.delete')
                            <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete('{{ $faq->id }}')" wire:confirm="{{ __('Delete this question?') }}" />
                        @endcan
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5">{{ __('No questions yet.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="faq-form" class="w-full md:w-[40rem]">
        <form wire:submit="save" class="space-y-4">
            <flux:heading size="lg">{{ $editingId ? __('Edit question') : __('New question') }}</flux:heading>
            <flux:input wire:model="question" :label="__('Question')" required />
            <div wire:key="faq-answer-{{ $formVersion }}">
                <x-admin.rich-text model="answer" :label="__('Answer')" />
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="group" :label="__('Group')" :placeholder="__('e.g. Pendaftaran')" />
                <flux:input wire:model="sortOrder" type="number" min="0" :label="__('Order')" />
            </div>
            <div class="flex justify-end"><flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button></div>
        </form>
    </flux:modal>
</div>
