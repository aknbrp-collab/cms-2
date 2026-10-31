<?php

use App\Enums\ContentStatus;
use App\Enums\PostType;
use App\Models\Post;
use Flux\Flux;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Posts')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $type = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Post::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status', 'type'], true)) {
            $this->resetPage();
        }
    }

    /**
     * @return LengthAwarePaginator<int, Post>
     */
    #[Computed]
    public function posts(): LengthAwarePaginator
    {
        return Post::query()
            ->with(['site', 'category', 'author'])
            ->when($this->search !== '', fn ($query) => $query->whereLike('title', "%{$this->search}%"))
            ->when(ContentStatus::tryFrom($this->status), fn ($query, ContentStatus $status) => $query->withStatus($status))
            ->when(PostType::tryFrom($this->type), fn ($query, PostType $type) => $query->where('type', $type->value))
            ->latest('updated_at')
            ->paginate(20);
    }

    public function delete(string $id): void
    {
        $post = Post::query()->findOrFail($id);
        $this->authorize('delete', $post);

        $post->delete();

        Flux::toast(text: __('Post moved to trash.'));
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Posts') }}</flux:heading>
            <flux:text class="mt-1">{{ __('News, announcements and articles.') }}</flux:text>
        </div>
        @can('create', App\Models\Post::class)
            <flux:button variant="primary" icon="plus" :href="route('admin.posts.create')" wire:navigate>{{ __('New post') }}</flux:button>
        @endcan
    </div>

    <div class="flex flex-wrap gap-3">
        <flux:input wire:model.live.debounce.300ms="search" :placeholder="__('Search title')" icon="magnifying-glass" class="max-w-xs" />
        <flux:select wire:model.live="status" class="max-w-44">
            <option value="">{{ __('All statuses') }}</option>
            @foreach (ContentStatus::cases() as $case)
                <option value="{{ $case->value }}">{{ $case->label() }}</option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="type" class="max-w-44">
            <option value="">{{ __('All types') }}</option>
            @foreach (PostType::cases() as $case)
                <option value="{{ $case->value }}">{{ $case->label() }}</option>
            @endforeach
        </flux:select>
    </div>

    <flux:table :paginate="$this->posts">
        <flux:table.columns>
            <flux:table.column>{{ __('Title') }}</flux:table.column>
            @unless (current_site())
                <flux:table.column>{{ __('Site') }}</flux:table.column>
            @endunless
            <flux:table.column>{{ __('Type') }}</flux:table.column>
            <flux:table.column>{{ __('Category') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column>{{ __('Updated') }}</flux:table.column>
            <flux:table.column />
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->posts as $post)
                <flux:table.row :key="$post->id">
                    <flux:table.cell class="max-w-md">
                        <a href="{{ route('admin.posts.edit', $post) }}" wire:navigate class="font-medium hover:underline">{{ $post->title }}</a>
                        <div class="text-xs text-zinc-500">{{ $post->author?->name }}</div>
                    </flux:table.cell>
                    @unless (current_site())
                        <flux:table.cell>{{ $post->site->name }}</flux:table.cell>
                    @endunless
                    <flux:table.cell>{{ $post->type->label() }}</flux:table.cell>
                    <flux:table.cell>{{ $post->category?->name }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$post->status->color()">{{ $post->isScheduled() ? __('Scheduled') : $post->status->label() }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell class="whitespace-nowrap">{{ $post->updated_at?->diffForHumans() }}</flux:table.cell>
                    <flux:table.cell align="end">
                        @can('delete', $post)
                            <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete('{{ $post->id }}')" wire:confirm="{{ __('Move this post to the trash?') }}" />
                        @endcan
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7">{{ __('No posts found.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
