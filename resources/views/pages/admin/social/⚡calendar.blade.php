<?php

use App\Models\SocialPost;
use App\Social\SocialPublisher;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Content calendar')] class extends Component {
    #[Url]
    public string $view = 'month';

    #[Url]
    public string $date = '';

    public function mount(): void
    {
        $this->date = $this->date !== '' ? CarbonImmutable::parse($this->date)->toDateString() : today()->toDateString();
    }

    #[Computed]
    public function anchor(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->date);
    }

    /**
     * Days shown: full weeks (Monday first) of the month, or one week.
     *
     * @return list<CarbonImmutable>
     */
    #[Computed]
    public function days(): array
    {
        [$start, $end] = $this->view === 'week'
            ? [$this->anchor->startOfWeek(), $this->anchor->endOfWeek()]
            : [$this->anchor->startOfMonth()->startOfWeek(), $this->anchor->endOfMonth()->endOfWeek()];

        $days = [];

        for ($day = $start; $day <= $end; $day = $day->addDay()) {
            $days[] = $day;
        }

        return $days;
    }

    /**
     * Posts keyed by day (Y-m-d), by publish or scheduled time.
     *
     * @return Collection<string, Collection<int, SocialPost>>
     */
    #[Computed]
    public function postsByDay(): Collection
    {
        $first = $this->days[0]->startOfDay();
        $last = Arr::last($this->days)->endOfDay();

        return SocialPost::query()
            ->with('targets.account')
            ->whereIn('site_id', Auth::user()->accessibleSites()->modelKeys())
            ->where(fn ($query) => $query->whereBetween('scheduled_at', [$first, $last])->orWhereBetween('published_at', [$first, $last]))
            ->get()
            ->sortBy(fn (SocialPost $post) => $post->published_at ?? $post->scheduled_at)
            ->groupBy(fn (SocialPost $post) => ($post->published_at ?? $post->scheduled_at)->toDateString());
    }

    public function shift(int $direction): void
    {
        $this->date = ($this->view === 'week' ? $this->anchor->addWeeks($direction) : $this->anchor->addMonthsNoOverflow($direction))->toDateString();
    }

    public function today(): void
    {
        $this->date = today()->toDateString();
    }

    /**
     * Drag and drop: move a post to another day, keeping its time.
     */
    public function move(string $postId, string $day, SocialPublisher $publisher): void
    {
        $post = SocialPost::query()->whereIn('site_id', Auth::user()->accessibleSites()->modelKeys())->findOrFail($postId);
        $time = $post->scheduled_at ?? now()->setTime(9, 0);
        $target = CarbonImmutable::parse($day)->setTime($time->hour, $time->minute);

        try {
            $publisher->reschedule($post, Auth::user(), $target);
        } catch (ValidationException $e) {
            Flux::toast(variant: 'danger', text: collect($e->errors())->flatten()->first());

            return;
        }

        unset($this->postsByDay);
        Flux::toast(variant: 'success', text: __('Moved to :date.', ['date' => $target->translatedFormat('d M Y H:i')]));
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Content calendar') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Drag a draft or scheduled post to another day to reschedule it.') }}</flux:text>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <flux:button size="sm" icon="chevron-left" wire:click="shift(-1)" />
            <flux:button size="sm" wire:click="today">{{ __('Today') }}</flux:button>
            <flux:button size="sm" icon="chevron-right" wire:click="shift(1)" />
            <flux:select size="sm" wire:model.live="view" class="w-32">
                <option value="month">{{ __('Month') }}</option>
                <option value="week">{{ __('Week') }}</option>
            </flux:select>
            @can('social_posts.create')
                @if (current_site())
                    <flux:button size="sm" variant="primary" icon="plus" :href="route('admin.social.create')" wire:navigate>{{ __('New post') }}</flux:button>
                @endif
            @endcan
        </div>
    </div>

    <flux:heading size="lg">
        {{ $view === 'week' ? $this->days[0]->translatedFormat('d M').' – '.Arr::last($this->days)->translatedFormat('d M Y') : $this->anchor->translatedFormat('F Y') }}
    </flux:heading>

    <div class="overflow-x-auto">
        <div class="grid min-w-[56rem] grid-cols-7 overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
            @foreach (['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'] as $dayName)
                <div class="border-b border-zinc-200 bg-zinc-50 px-2 py-1.5 text-xs font-semibold text-zinc-500 dark:border-zinc-700 dark:bg-zinc-900">{{ $dayName }}</div>
            @endforeach

            @foreach ($this->days as $day)
                @php($key = $day->toDateString())
                <div
                    wire:key="day-{{ $key }}"
                    x-data="{ over: false }"
                    x-on:dragover.prevent="over = true"
                    x-on:dragleave="over = false"
                    x-on:drop.prevent="over = false; $wire.move($event.dataTransfer.getData('text/post'), '{{ $key }}')"
                    :class="over && 'bg-emerald-50 dark:bg-emerald-950'"
                    @class([
                        'border-b border-e border-zinc-100 p-1.5 dark:border-zinc-800',
                        'min-h-28' => $view === 'month',
                        'min-h-72' => $view === 'week',
                        'bg-zinc-50/60 dark:bg-zinc-900/40' => $view === 'month' && ! $day->isSameMonth($this->anchor),
                    ])
                >
                    <div @class(['mb-1 text-xs', 'inline-flex size-6 items-center justify-center rounded-full bg-emerald-700 font-bold text-white' => $day->isToday(), 'text-zinc-500' => ! $day->isToday()])>{{ $day->day }}</div>
                    <div class="space-y-1">
                        @foreach ($this->postsByDay->get($key, collect()) as $post)
                            <a
                                href="{{ route('admin.social.edit', $post) }}"
                                wire:navigate
                                wire:key="post-{{ $post->id }}"
                                @if ($post->status->isEditable()) draggable="true" x-on:dragstart="$event.dataTransfer.setData('text/post', '{{ $post->id }}')" @endif
                                @class([
                                    'block rounded-md border-s-4 bg-white px-1.5 py-1 text-xs shadow-sm dark:bg-zinc-800',
                                    'cursor-grab' => $post->status->isEditable(),
                                    'border-zinc-400' => $post->status->value === 'draft',
                                    'border-sky-500' => $post->status->value === 'scheduled',
                                    'border-amber-500' => $post->status->value === 'publishing',
                                    'border-emerald-600' => $post->status->value === 'published',
                                    'border-red-500' => in_array($post->status->value, ['failed', 'partially_failed'], true),
                                ])
                            >
                                <span class="font-semibold">{{ ($post->published_at ?? $post->scheduled_at)->format('H:i') }}</span>
                                <span class="line-clamp-2">{{ $post->caption }}</span>
                                <span class="text-[10px] text-zinc-500">{{ $post->targets->map(fn ($target) => $target->account->provider->label())->unique()->join(' · ') }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
