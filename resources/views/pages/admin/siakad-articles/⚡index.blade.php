<?php

use App\Enums\SiakadArticleStatus;
use App\Models\SiakadArticle;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('SIAKAD articles')] class extends Component {
    use WithPagination;

    #[Url]
    public string $status = '';

    #[Url]
    public string $search = '';

    public function mount(): void
    {
        $this->authorize('siakad_articles.view');
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['status', 'search'], true)) {
            $this->resetPage();
        }
    }

    /**
     * @return LengthAwarePaginator<int, SiakadArticle>
     */
    #[Computed]
    public function articles(): LengthAwarePaginator
    {
        return SiakadArticle::query()
            ->when(SiakadArticleStatus::tryFrom($this->status), fn ($query, SiakadArticleStatus $status) => $query->where('status', $status->value))
            ->when($this->search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->whereLike('title', "%{$this->search}%")
                ->orWhereLike('authors', "%{$this->search}%")))
            ->latest('received_at')
            ->paginate(20);
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('SIAKAD articles') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Articles sent by SIAKAD. Review and approve them before they appear in the website repository.') }}</flux:text>
    </div>

    <div class="flex flex-wrap gap-3">
        <flux:input wire:model.live.debounce.300ms="search" :placeholder="__('Title or author')" icon="magnifying-glass" class="max-w-xs" />
        <flux:select wire:model.live="status" class="max-w-52">
            <option value="">{{ __('All statuses') }}</option>
            @foreach (SiakadArticleStatus::cases() as $case)
                <option value="{{ $case->value }}">{{ $case->label() }}</option>
            @endforeach
        </flux:select>
    </div>

    <flux:table :paginate="$this->articles">
        <flux:table.columns>
            <flux:table.column>{{ __('Title') }}</flux:table.column>
            <flux:table.column>{{ __('Type') }}</flux:table.column>
            <flux:table.column>{{ __('Received') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->articles as $article)
                <flux:table.row :key="$article->id">
                    <flux:table.cell class="max-w-md">
                        <a href="{{ route('admin.siakad-articles.show', $article) }}" wire:navigate class="font-medium hover:underline">{{ $article->title }}</a>
                        <div class="truncate text-xs text-zinc-500">{{ $article->authors }} · SIAKAD #{{ $article->external_id }}</div>
                    </flux:table.cell>
                    <flux:table.cell>{{ $article->type->label() }}</flux:table.cell>
                    <flux:table.cell class="whitespace-nowrap">{{ $article->received_at->diffForHumans() }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$article->status->color()">{{ $article->status->label() }}</flux:badge>
                        @if ($article->updated_after_approval)
                            <flux:badge size="sm" color="amber">{{ __('Updated') }}</flux:badge>
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="4">{{ __('No articles received yet.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
