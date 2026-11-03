<?php

use App\Enums\ContentStatus;
use App\Models\AuditLog;
use App\Support\ContentTypes;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] class extends Component {
    #[Computed]
    public function site(): ?Site
    {
        return current_site();
    }

    #[Computed]
    public function userCount(): int
    {
        return User::query()->count();
    }

    /**
     * @return Collection<int, DatabaseNotification>
     */
    #[Computed]
    public function notifications(): Collection
    {
        return Auth::user()->unreadNotifications()->limit(10)->get();
    }

    #[Computed]
    public function awaitingReview(): int
    {
        return collect(ContentTypes::REVIEWABLE)
            ->sum(fn (string $modelClass) => $modelClass::query()->withStatus(ContentStatus::UnderReview)->count());
    }

    public function markAllRead(): void
    {
        Auth::user()->unreadNotifications()->update(['read_at' => now()]);
        unset($this->notifications);
    }

    /**
     * @return Collection<int, AuditLog>
     */
    #[Computed]
    public function recentActivity(): Collection
    {
        return AuditLog::query()
            ->with('user')
            ->when($this->site, fn ($query, Site $site) => $query->where('site_id', $site->id))
            ->latest('created_at')
            ->limit(8)
            ->get();
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Dashboard') }}</flux:heading>
        <flux:text class="mt-1">
            {{ $this->site ? $this->site->name : __('All sites') }}
        </flux:text>
    </div>

    <div class="grid gap-4 md:grid-cols-3">
        <flux:card>
            <flux:text>{{ __('Your roles') }}</flux:text>
            <div class="mt-2 flex flex-wrap gap-1">
                @foreach (Auth::user()->cmsRoles() as $role)
                    <flux:badge size="sm" color="emerald">{{ $role->label() }}</flux:badge>
                @endforeach
            </div>
        </flux:card>

        @can('users.view')
            <flux:card>
                <flux:text>{{ __('Users') }}</flux:text>
                <flux:heading size="xl" class="mt-2">{{ $this->userCount }}</flux:heading>
            </flux:card>
        @endcan

        @can('review-content')
            <flux:card>
                <flux:text>{{ __('Waiting for review') }}</flux:text>
                <div class="mt-2 flex items-center justify-between">
                    <flux:heading size="xl">{{ $this->awaitingReview }}</flux:heading>
                    <flux:link :href="route('admin.review')" wire:navigate>{{ __('Open queue') }}</flux:link>
                </div>
            </flux:card>
        @endcan

        <flux:card>
            <flux:text>{{ __('Sites you manage') }}</flux:text>
            <flux:heading size="lg" class="mt-2">
                {{ Auth::user()->accessibleSites()->pluck('name')->join(', ') }}
            </flux:heading>
        </flux:card>
    </div>

    @if ($this->notifications->isNotEmpty())
        <flux:card>
            <div class="mb-4 flex items-center justify-between">
                <flux:heading size="lg">{{ __('Notifications') }}</flux:heading>
                <flux:button size="sm" variant="ghost" wire:click="markAllRead">{{ __('Mark all as read') }}</flux:button>
            </div>

            @foreach ($this->notifications as $notification)
                <div class="border-b border-zinc-100 py-2 text-sm last:border-0 dark:border-zinc-700" wire:key="note-{{ $notification->id }}">
                    @php
                        $data = $notification->data;
                        $contentClass = Illuminate\Database\Eloquent\Relations\Relation::getMorphedModel($data['content_type'] ?? '');
                    @endphp
                    @if (is_string($data['url'] ?? null))
                        <a href="{{ $data['url'] }}" wire:navigate class="hover:underline">{{ $data['message'] ?? '' }}</a>
                    @elseif ($contentClass && is_subclass_of($contentClass, App\Contracts\Reviewable::class))
                        <a href="{{ route($contentClass::adminEditRoute(), $data['content_id'] ?? '') }}" wire:navigate class="hover:underline">{{ $data['message'] ?? '' }}</a>
                    @else
                        <span>{{ $data['message'] ?? '' }}</span>
                    @endif
                    @if (! empty($data['note']))
                        <div class="text-zinc-500">“{{ $data['note'] }}”</div>
                    @endif
                    <div class="text-xs text-zinc-500">{{ $notification->created_at?->diffForHumans() }}</div>
                </div>
            @endforeach
        </flux:card>
    @endif

    @can('audit_logs.view')
        <flux:card>
            <div class="mb-4 flex items-center justify-between">
                <flux:heading size="lg">{{ __('Recent activity') }}</flux:heading>
                <flux:link :href="route('admin.audit-logs')" wire:navigate>{{ __('View all') }}</flux:link>
            </div>

            @forelse ($this->recentActivity as $log)
                <div class="flex items-center justify-between border-b border-zinc-100 py-2 text-sm last:border-0 dark:border-zinc-700" wire:key="log-{{ $log->id }}">
                    <span>
                        <span class="font-medium">{{ $log->user?->name ?? __('System') }}</span>
                        <span class="text-zinc-500" title="{{ $log->event }}">{{ App\Support\AdminLabels::event($log->event) }}</span>
                        @if ($log->auditable_type)
                            <span class="text-zinc-500">{{ App\Support\AdminLabels::type($log->auditable_type) }}</span>
                        @endif
                    </span>
                    <span class="text-zinc-500">{{ $log->created_at->diffForHumans() }}</span>
                </div>
            @empty
                <flux:text>{{ __('No activity yet.') }}</flux:text>
            @endforelse
        </flux:card>
    @endcan
</div>
