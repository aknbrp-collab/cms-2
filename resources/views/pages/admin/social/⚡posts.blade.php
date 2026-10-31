<?php

use App\Enums\SocialPostStatus;
use App\Models\SocialPost;
use Flux\Flux;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Social posts')] class extends Component {
    use WithPagination;

    #[Url]
    public string $status = '';

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, SocialPost>
     */
    #[Computed]
    public function posts(): LengthAwarePaginator
    {
        return SocialPost::query()
            ->with(['targets.account', 'site'])
            ->whereIn('site_id', Auth::user()->accessibleSites()->modelKeys())
            ->when(SocialPostStatus::tryFrom($this->status), fn ($query, SocialPostStatus $status) => $query->where('status', $status->value))
            ->orderByRaw('coalesce(scheduled_at, created_at) desc')
            ->paginate(20);
    }

    public function delete(string $id): void
    {
        $this->authorize('social_posts.delete');
        $post = SocialPost::query()->whereIn('site_id', Auth::user()->accessibleSites()->modelKeys())->findOrFail($id);

        abort_unless($post->status->isEditable(), 422);
        $post->delete();

        Flux::toast(text: __('Deleted.'));
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Social posts') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Write once, publish to several accounts.') }}</flux:text>
        </div>
        <div class="flex gap-2">
            <flux:button icon="calendar-days" :href="route('admin.social.calendar')" wire:navigate>{{ __('Calendar') }}</flux:button>
            @can('social_posts.create')
                @if (current_site())
                    <flux:button variant="primary" icon="plus" :href="route('admin.social.create')" wire:navigate>{{ __('New post') }}</flux:button>
                @endif
            @endcan
        </div>
    </div>

    <flux:navbar class="overflow-x-auto">
        <flux:navbar.item as="button" type="button" wire:click="$set('status', '')" :current="$status === ''">{{ __('All') }}</flux:navbar.item>
        @foreach (SocialPostStatus::cases() as $case)
            <flux:navbar.item as="button" type="button" wire:click="$set('status', '{{ $case->value }}')" :current="$status === $case->value">{{ $case->label() }}</flux:navbar.item>
        @endforeach
    </flux:navbar>

    <flux:table :paginate="$this->posts">
        <flux:table.columns>
            <flux:table.column>{{ __('Post') }}</flux:table.column>
            <flux:table.column>{{ __('Accounts') }}</flux:table.column>
            <flux:table.column>{{ __('When') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column />
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->posts as $post)
                <flux:table.row :key="$post->id">
                    <flux:table.cell class="max-w-md">
                        <a href="{{ route('admin.social.edit', $post) }}" wire:navigate class="line-clamp-2 font-medium hover:underline">{{ $post->caption }}</a>
                        @unless (current_site())
                            <div class="text-xs text-zinc-500">{{ $post->site->name }}</div>
                        @endunless
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex flex-wrap gap-1">
                            @foreach ($post->targets as $target)
                                <flux:badge size="sm" :color="$target->status->color()" :title="$target->last_error">{{ $target->account->provider->label() }}</flux:badge>
                            @endforeach
                        </div>
                    </flux:table.cell>
                    <flux:table.cell class="whitespace-nowrap">{{ ($post->published_at ?? $post->scheduled_at)?->translatedFormat('d M Y H:i') ?? '–' }}</flux:table.cell>
                    <flux:table.cell><flux:badge size="sm" :color="$post->status->color()">{{ $post->status->label() }}</flux:badge></flux:table.cell>
                    <flux:table.cell align="end">
                        @if ($post->status->isEditable())
                            @can('social_posts.delete')
                                <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete('{{ $post->id }}')" wire:confirm="{{ __('Delete this post?') }}" />
                            @endcan
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5">{{ __('No posts yet.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
