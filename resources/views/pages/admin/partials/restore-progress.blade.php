{{-- Progress of the last restore on the Data page. Expects $restore (RestoreStatus::get()) and $restoring. --}}
@php
    $steps = [
        'checking' => __('Check the server'),
        'backing_up' => __('Back up the current data'),
        'preparing' => __('Unpack and verify the backup'),
        'restoring_database' => __('Replace the database'),
        'restoring_files' => __('Put back uploaded files'),
    ];
    $state = $restore['state'] ?? 'queued';
    $keys = array_keys($steps);
    $current = array_search($state, $keys, true);
@endphp

<flux:card class="space-y-3">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <flux:heading class="flex items-center gap-2">
            <flux:icon.arrow-uturn-left class="size-5 text-zinc-500" />
            {{ __('Restore of :file', ['file' => $restore['file'] ?? '']) }}
        </flux:heading>
        @if (! empty($restore['by']))
            <flux:text size="sm">{{ __('Started by :name', ['name' => $restore['by']]) }}@if (! empty($restore['started_at'])) · {{ \Illuminate\Support\Carbon::parse($restore['started_at'])->diffForHumans() }}@endif</flux:text>
        @endif
    </div>

    @if ($state === 'done')
        <flux:callout icon="check-circle" color="emerald" :heading="__('Restore finished')" :text="__('The data of :file is back, with :count uploaded files. The sites are online again.', ['file' => $restore['file'] ?? '', 'count' => $restore['files'] ?? 0])" />
    @elseif ($state === 'failed')
        <flux:callout icon="exclamation-triangle" color="red" :heading="__('Restore failed')" :text="$restore['message'] ?? ''" />
    @else
        <ol class="space-y-1.5 text-sm">
            @if ($state === 'queued')
                <li class="flex items-center gap-2 text-zinc-500"><flux:icon.clock class="size-4" /> {{ __('Waiting for the background worker…') }}</li>
            @endif
            @foreach ($steps as $key => $label)
                @php $index = array_search($key, $keys, true); @endphp
                <li @class(['flex items-center gap-2', 'font-medium' => $key === $state, 'text-zinc-400' => $current === false || $index > $current])>
                    @if ($current !== false && $index < $current)
                        <flux:icon.check-circle class="size-4 text-emerald-600" />
                    @elseif ($key === $state)
                        <flux:icon.arrow-path class="size-4 animate-spin text-sky-600" />
                    @else
                        <flux:icon.minus-circle class="size-4" />
                    @endif
                    {{ $label }}
                </li>
            @endforeach
        </ol>
        <flux:text size="sm">{{ __('Keep this page open or come back later; the restore continues in the background.') }}</flux:text>
    @endif
</flux:card>
