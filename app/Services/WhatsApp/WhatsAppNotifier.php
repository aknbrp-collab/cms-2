<?php

namespace App\Services\WhatsApp;

use App\Enums\WhatsAppEvent;
use App\Jobs\SendAdmissionWhatsApp;
use App\Models\AdmissionMessage;
use App\Models\AdmissionRegistration;
use App\Models\Site;
use App\Support\WhatsApp;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Sends admission messages automatically through the site's WhatsApp
 * gateway, when one is set up and the event is switched on.
 */
class WhatsAppNotifier
{
    /**
     * Gateways an admin can choose, by settings value.
     */
    public const DRIVERS = ['fonnte' => 'Fonnte'];

    /**
     * Events that may go out automatically (the reminder is always sent by hand).
     */
    public const AUTO_EVENTS = [WhatsAppEvent::Received, WhatsAppEvent::Verified, WhatsAppEvent::Accepted, WhatsAppEvent::Rejected, WhatsAppEvent::NeedsFix];

    /**
     * The site's gateway, or null when it is off or has no (readable) key.
     */
    public function gatewayFor(Site $site): ?Gateway
    {
        if (! $site->setting('whatsapp.gateway_enabled')) {
            return null;
        }

        $token = $this->token($site);

        if ($token === null) {
            return null;
        }

        return match ($site->setting('whatsapp.gateway_driver')) {
            'fonnte' => new FonnteGateway($token),
            default => null,
        };
    }

    public function sendsAutomatically(Site $site, WhatsAppEvent $event): bool
    {
        return in_array($event, self::AUTO_EVENTS, true)
            && (bool) $site->setting("whatsapp.auto_{$event->value}")
            && $this->gatewayFor($site) !== null;
    }

    /**
     * Queue the event's message for the guardian. Returns the logged message,
     * or null when it is not sent automatically (the admin sends it by hand).
     */
    public function notify(WhatsAppEvent $event, AdmissionRegistration $registration): ?AdmissionMessage
    {
        $registration->loadMissing('site');
        $phone = WhatsApp::normalizePhone($registration->parent_phone);

        if ($phone === null || ! $this->sendsAutomatically($registration->site, $event)) {
            return null;
        }

        $message = $registration->messages()->create([
            'event' => $event,
            'channel' => 'gateway',
            'status' => 'queued',
            'phone' => $phone,
            'body' => WhatsApp::message($event, $registration),
        ]);

        SendAdmissionWhatsApp::dispatch($message->id)->afterCommit();

        return $message;
    }

    /**
     * Encrypted gateway key from the settings, decrypted.
     */
    public function token(Site $site): ?string
    {
        $stored = (string) $site->setting('whatsapp.gateway_token');

        if ($stored === '') {
            return null;
        }

        try {
            return Crypt::decryptString($stored);
        } catch (DecryptException) {
            return null;
        }
    }
}
