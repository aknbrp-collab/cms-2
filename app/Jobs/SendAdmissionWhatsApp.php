<?php

namespace App\Jobs;

use App\Models\AdmissionMessage;
use App\Services\WhatsApp\WhatsAppNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Send a queued admission message through the site's WhatsApp gateway.
 * Network errors and rate limits are retried; a rejected message is marked
 * failed so the admin can send it by hand.
 */
class SendAdmissionWhatsApp implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [60, 300];

    public function __construct(public string $messageId) {}

    public function handle(WhatsAppNotifier $notifier): void
    {
        $message = AdmissionMessage::query()->with('registration.site')->find($this->messageId);

        if ($message === null || $message->status === 'sent') {
            return;
        }

        $gateway = $notifier->gatewayFor($message->registration->site);

        if ($gateway === null) {
            $message->forceFill(['status' => 'failed', 'error' => 'The WhatsApp gateway is switched off or has no key.'])->save();

            return;
        }

        $result = $gateway->send($message->phone, $message->body);

        if ($result->ok) {
            $message->forceFill(['status' => 'sent', 'error' => null])->save();

            return;
        }

        if ($result->retry && $this->attempts() < $this->tries) {
            $message->forceFill(['error' => $result->error])->save();
            $this->release($this->backoff[$this->attempts() - 1] ?? 300);

            return;
        }

        $message->forceFill(['status' => 'failed', 'error' => $result->error])->save();
    }

    public function failed(?Throwable $exception): void
    {
        AdmissionMessage::query()->whereKey($this->messageId)->where('status', 'queued')
            ->update(['status' => 'failed', 'error' => mb_substr((string) $exception?->getMessage(), 0, 250) ?: 'Sending failed.']);
    }
}
