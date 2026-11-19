{{-- Clean-up of old data on the Data page (needs data.purge). --}}
@php
    $kinds = [
        'admissions' => [__('Admission registrations'), __('Registrations of admission periods that closed before the date, with their uploaded documents and WhatsApp messages. The admission periods themselves stay.')],
        'messages' => [__('WhatsApp message history'), __('Messages to guardians sent before the date.')],
        'integration_logs' => [__('Integration logs'), __('SIAKAD and social media logs from before the date.')],
    ];
    $purge = $this->purgeStatus;
@endphp

<flux:card class="space-y-4">
    <div>
        <flux:heading class="flex items-center gap-2"><flux:icon.trash class="size-5 text-zinc-500" /> {{ __('Clean up old data') }}</flux:heading>
        <flux:text size="sm" class="mt-1">{{ __('Permanently delete personal data you no longer need. A full backup is taken first. Audit logs are never deleted.') }}</flux:text>
    </div>

    @if ($purge)
        @if ($this->purging)
            <flux:callout icon="arrow-path" color="sky" :heading="__('Clean-up running…')" :text="($purge['state'] ?? '') === 'purging' ? __('Deleting…') : __('Backing up first…')" />
        @elseif (($purge['state'] ?? '') === 'failed')
            <flux:callout icon="exclamation-triangle" color="red" :heading="__('The last clean-up failed')" :text="$purge['message'] ?? ''" />
        @elseif (($purge['state'] ?? '') === 'done')
            <flux:callout icon="check-circle" color="emerald" :heading="__('Clean-up finished')">
                <flux:callout.text>
                    {{ $kinds[$purge['kind']][0] ?? $purge['kind'] }} · {{ __('before :date', ['date' => \Illuminate\Support\Carbon::parse($purge['before'])->translatedFormat('d M Y')]) }}:
                    @foreach ($purge['counts'] ?? [] as $what => $count)
                        {{ $count }} {{ ['registrations' => __('registrations'), 'documents' => __('documents'), 'messages' => __('messages'), 'integration_logs' => __('log entries')][$what] ?? $what }}@if (! $loop->last), @endif
                    @endforeach
                    {{ __('deleted.') }}
                </flux:callout.text>
            </flux:callout>
        @endif
    @endif

    <div class="grid gap-4 sm:grid-cols-[1fr_12rem]">
        <flux:radio.group wire:model.live="purgeKind" :label="__('What')">
            @foreach ($kinds as $kind => [$label, $description])
                <flux:radio :value="$kind" :label="$label" :description="$description" />
            @endforeach
        </flux:radio.group>
        <flux:input type="date" wire:model.live="purgeBefore" :label="__('Older than')" :max="today()->toDateString()" />
    </div>

    <div>
        <flux:button icon="magnifying-glass" wire:click="previewPurge" :disabled="$this->purging">{{ __('Show what would be deleted') }}</flux:button>
        <flux:error name="purgeBefore" />
    </div>

    @if ($purgePreview !== null)
        @if ($purgePreview['total'] === 0)
            <flux:text>{{ __('Nothing to delete before this date.') }}</flux:text>
        @else
            <div class="rounded-lg border border-red-200 bg-red-50/60 p-4 dark:border-red-900 dark:bg-red-950/30">
                <p class="font-medium">
                    @if ($purgeKind === 'admissions')
                        {{ trans_choice(':count registration|:count registrations', $purgePreview['total']) }},
                        {{ trans_choice(':count document|:count documents', $purgePreview['details']['documents'] ?? 0) }},
                        {{ trans_choice(':count message|:count messages', $purgePreview['details']['messages'] ?? 0) }}
                    @elseif ($purgeKind === 'messages')
                        {{ trans_choice(':count message|:count messages', $purgePreview['total']) }}
                    @else
                        {{ trans_choice(':count log entry|:count log entries', $purgePreview['total']) }}
                    @endif
                    {{ __('will be deleted permanently.') }}
                </p>
                @if ($purgePreview['periods'] !== [])
                    <ul class="mt-2 space-y-0.5 text-sm text-zinc-600 dark:text-zinc-300">
                        @foreach ($purgePreview['periods'] as $period)
                            <li>{{ $period['site'] }} · {{ $period['title'] }} ({{ __('closed :date', ['date' => \Illuminate\Support\Carbon::parse($period['closes_on'])->translatedFormat('d M Y')]) }}): {{ trans_choice(':count registration|:count registrations', $period['registrations']) }}</li>
                        @endforeach
                    </ul>
                @endif

                <form wire:submit="startPurge" class="mt-4 grid gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                    <flux:input type="password" wire:model="purgePassword" :label="__('Your password')" autocomplete="current-password" />
                    <flux:input wire:model="purgeConfirmation" :label="__('Type DELETE to confirm')" autocomplete="off" class="font-mono" />
                    <flux:button type="submit" variant="danger" icon="trash">{{ __('Delete permanently') }}</flux:button>
                </form>
                <flux:error name="purgePassword" />
                <flux:error name="purgeConfirmation" />
            </div>
        @endif
    @endif
</flux:card>
