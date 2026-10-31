<?php

use App\Enums\ContentStatus;
use App\Contracts\Reviewable;
use App\Support\ContentTypes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Review queue')] class extends Component {
    /**
     * Content waiting for review that the current user may approve.
     *
     * @return Collection<int, Reviewable&Model>
     */
    #[Computed]
    public function items(): Collection
    {
        $user = Auth::user();

        return collect(ContentTypes::REVIEWABLE)
            ->flatMap(fn (string $modelClass) => $modelClass::query()
                ->with(['site', 'author'])
                ->withStatus(ContentStatus::UnderReview)
                ->oldest('submitted_at')
                ->get())
            ->filter(fn (Model $content) => $user->can('publish', $content))
            ->sortBy('submitted_at')
            ->values();
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Review queue') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Content submitted for review, oldest first.') }}</flux:text>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Title') }}</flux:table.column>
            <flux:table.column>{{ __('Kind') }}</flux:table.column>
            <flux:table.column>{{ __('Site') }}</flux:table.column>
            <flux:table.column>{{ __('Author') }}</flux:table.column>
            <flux:table.column>{{ __('Submitted') }}</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->items as $item)
                <flux:table.row :key="$item->getMorphClass().'-'.$item->id">
                    <flux:table.cell>
                        <a href="{{ route($item::adminEditRoute(), $item) }}" wire:navigate class="font-medium hover:underline">{{ $item->title }}</a>
                    </flux:table.cell>
                    <flux:table.cell>{{ $item::contentLabel() }}</flux:table.cell>
                    <flux:table.cell>{{ $item->site->name }}</flux:table.cell>
                    <flux:table.cell>{{ $item->author?->name }}</flux:table.cell>
                    <flux:table.cell class="whitespace-nowrap">{{ $item->submitted_at?->diffForHumans() }}</flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5">{{ __('Nothing is waiting for review.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
