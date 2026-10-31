<?php

use App\Enums\LegalCategory;
use App\Enums\LegalQuestionStatus;
use App\Models\LegalQuestion;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Tanya Hukum')] class extends Component {
    use WithPagination;

    #[Url]
    public string $status = 'submitted';

    #[Url]
    public string $category = '';

    #[Url]
    public string $search = '';

    public function mount(): void
    {
        $this->authorize('legal_questions.view');
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['status', 'category', 'search'], true)) {
            $this->resetPage();
        }
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function counts(): array
    {
        return LegalQuestion::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')->map(fn ($total) => (int) $total)->all();
    }

    /**
     * @return LengthAwarePaginator<int, LegalQuestion>
     */
    #[Computed]
    public function questions(): LengthAwarePaginator
    {
        return LegalQuestion::query()
            ->when(LegalQuestionStatus::tryFrom($this->status), fn ($query, LegalQuestionStatus $status) => $query->where('status', $status->value))
            ->when(LegalCategory::tryFrom($this->category), fn ($query, LegalCategory $category) => $query->where('category', $category->value))
            ->when($this->search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->whereLike('question', "%{$this->search}%")
                ->orWhereLike('tracking_code', "%{$this->search}%")
                ->orWhereLike('title', "%{$this->search}%")))
            ->oldest()
            ->paginate(20);
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Tanya Hukum') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Questions from the website. Moderate, forward to SIAKAD, then publish the answer.') }}</flux:text>
    </div>

    <flux:navbar class="overflow-x-auto">
        <flux:navbar.item as="button" type="button" wire:click="$set('status', '')" :current="$status === ''">{{ __('All') }}</flux:navbar.item>
        @foreach (LegalQuestionStatus::cases() as $case)
            <flux:navbar.item as="button" type="button" wire:click="$set('status', '{{ $case->value }}')" :current="$status === $case->value" :badge="$this->counts[$case->value] ?? null">{{ $case->label() }}</flux:navbar.item>
        @endforeach
    </flux:navbar>

    <div class="flex flex-wrap gap-3">
        <flux:input wire:model.live.debounce.300ms="search" :placeholder="__('Search question or code')" icon="magnifying-glass" class="max-w-xs" />
        <flux:select wire:model.live="category" class="max-w-56">
            <option value="">{{ __('All categories') }}</option>
            @foreach (LegalCategory::cases() as $case)
                <option value="{{ $case->value }}">{{ $case->label() }}</option>
            @endforeach
        </flux:select>
    </div>

    <flux:table :paginate="$this->questions">
        <flux:table.columns>
            <flux:table.column>{{ __('Question') }}</flux:table.column>
            <flux:table.column>{{ __('Category') }}</flux:table.column>
            <flux:table.column>{{ __('Submitted') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->questions as $question)
                <flux:table.row :key="$question->id">
                    <flux:table.cell class="max-w-lg">
                        <a href="{{ route('admin.legal-questions.show', $question) }}" wire:navigate class="font-medium hover:underline">{{ $question->title ?: Str::limit($question->question, 90) }}</a>
                        <div class="font-mono text-xs text-zinc-500">{{ $question->tracking_code }}</div>
                    </flux:table.cell>
                    <flux:table.cell>{{ $question->category->label() }}</flux:table.cell>
                    <flux:table.cell class="whitespace-nowrap">{{ $question->created_at?->diffForHumans() }}</flux:table.cell>
                    <flux:table.cell><flux:badge size="sm" :color="$question->status->color()">{{ $question->status->label() }}</flux:badge></flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="4">{{ __('No questions here.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
