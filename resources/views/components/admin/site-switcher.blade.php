@php
    $user = auth()->user();
    $sites = $user->accessibleSites();
    $current = current_site();
@endphp

@if ($sites->count() > 1 || $user->canAccessAllSites())
    <form method="POST" action="{{ route('admin.switch-site') }}" class="px-1" data-test="site-switcher">
        @csrf
        <flux:select name="site" size="sm" :label="__('Site')" x-on:change="$el.closest('form').submit()">
            @if ($user->canAccessAllSites())
                <option value="{{ \App\Http\Middleware\SetAdminSite::ALL }}" @selected($current === null)>{{ __('All sites') }}</option>
            @endif
            @foreach ($sites as $site)
                <option value="{{ $site->id }}" @selected($current?->is($site))>{{ $site->name }}</option>
            @endforeach
        </flux:select>
    </form>
@elseif ($current)
    <div class="px-2">
        <flux:text size="sm">{{ __('Site') }}</flux:text>
        <flux:heading size="sm">{{ $current->name }}</flux:heading>
    </div>
@endif
