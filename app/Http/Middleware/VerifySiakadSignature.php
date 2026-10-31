<?php

namespace App\Http\Middleware;

use App\Models\IntegrationLog;
use App\Siakad\IntegrationLogger;
use App\Siakad\Signature;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Accept only SIAKAD webhooks signed with the shared secret and not older
 * than the configured tolerance (replay protection).
 */
class VerifySiakadSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('siakad.webhook_secret');

        $valid = $secret !== '' && Signature::verify(
            $request->getContent(),
            (string) $request->header(Signature::TIMESTAMP_HEADER),
            (string) $request->header(Signature::SIGNATURE_HEADER),
            $secret,
            (int) config('siakad.tolerance'),
        );

        if (! $valid) {
            IntegrationLogger::log(
                IntegrationLog::INBOUND,
                (string) $request->route()?->getName(),
                IntegrationLog::REJECTED,
                httpStatus: 401,
                error: $secret === '' ? 'SIAKAD_WEBHOOK_SECRET is not configured.' : 'Invalid or expired signature.',
            );

            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        return $next($request);
    }
}
