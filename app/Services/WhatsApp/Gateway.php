<?php

namespace App\Services\WhatsApp;

/**
 * A WhatsApp sending service (gateway). Implementations must not throw for
 * a rejected message; they report it in the result instead.
 */
interface Gateway
{
    /**
     * Send a text message to a number in international form (628…).
     */
    public function send(string $phone, string $message): GatewayResult;
}
