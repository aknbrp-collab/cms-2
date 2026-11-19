{{-- WhatsApp messages to the guardian in the registration dialog. Expects $registration. --}}
@php
    use App\Enums\WhatsAppEvent;

    $phone = $this->waPhone;
    $history = $registration->messages->take(10);
@endphp

<div class="space-y-3 border-t border-zinc-200 pt-4 dark:border-zinc-700">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <flux:heading class="flex items-center gap-2"><flux:icon.chat-bubble-left-ellipsis class="size-5 text-emerald-600" /> {{ __('WhatsApp to the guardian') }}</flux:heading>
        @if ($phone)
            <flux:text size="sm" class="font-mono">+{{ $phone }}</flux:text>
        @endif
    </div>

    @if (! $phone)
        <flux:callout icon="exclamation-triangle" color="amber" :text="__('The parent phone number (:phone) is not a valid WhatsApp number.', ['phone' => $registration->parent_phone])" />
    @else
        @can('admissions.update')
            @if ($waEvent === '')
                <div class="flex flex-wrap gap-2">
                    @foreach (WhatsAppEvent::cases() as $event)
                        <flux:button size="xs" wire:click="composeWhatsApp('{{ $event->value }}')">{{ $event->label() }}</flux:button>
                    @endforeach
                </div>
            @else
                <div
                    class="space-y-3 rounded-lg border border-emerald-200 bg-emerald-50/60 p-3 dark:border-emerald-900 dark:bg-emerald-950/30"
                    x-data="{ message: $wire.entangle('waMessage') }"
                >
                    <div class="flex items-center justify-between gap-2">
                        <flux:badge size="sm" color="emerald">{{ WhatsAppEvent::from($waEvent)->label() }}</flux:badge>
                        <flux:text size="sm">{{ __('Check the text, then open WhatsApp and press Send.') }}</flux:text>
                    </div>
                    <flux:textarea x-model="message" rows="8" class="text-sm" :aria-label="__('Message')" />
                    <flux:error name="waMessage" />
                    <div class="flex flex-wrap justify-end gap-2">
                        <flux:button size="sm" variant="ghost" wire:click="cancelWhatsApp">{{ __('Cancel') }}</flux:button>
                        <a
                            x-bind:href="'https://wa.me/{{ $phone }}?text=' + encodeURIComponent(message)"
                            target="_blank"
                            rel="noopener"
                            x-on:click="$wire.logWhatsApp()"
                            class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-emerald-700"
                        >
                            <flux:icon.paper-airplane class="size-4" /> {{ __('Open WhatsApp') }}
                        </a>
                    </div>
                </div>
            @endif
        @endcan
    @endif

    @if ($history->isNotEmpty())
        <div class="space-y-1">
            <flux:text size="sm" class="font-medium">{{ __('Sent messages') }}</flux:text>
            <ul class="divide-y divide-zinc-100 text-sm dark:divide-zinc-800">
                @foreach ($history as $message)
                    <li class="flex flex-wrap items-center justify-between gap-2 py-1.5" title="{{ $message->body }}">
                        <span class="flex items-center gap-2">
                            <flux:badge size="sm" :color="$message->status === 'failed' ? 'red' : 'zinc'">{{ $message->event->label() }}</flux:badge>
                            <span class="text-zinc-500">{{ $message->channel === 'gateway' ? __('Sent automatically') : __('Opened by :name', ['name' => $message->user?->name ?? __('someone')]) }}</span>
                        </span>
                        <span class="text-xs text-zinc-500">{{ $message->created_at?->translatedFormat('d M Y H:i') }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
