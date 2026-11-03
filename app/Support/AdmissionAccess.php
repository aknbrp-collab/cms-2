<?php

namespace App\Support;

use App\Models\AdmissionRegistration;

/**
 * Session-bound access for an applicant to their own registration's
 * document step. Granted right after registering, or after entering the
 * registration number and birth date. Never part of a URL.
 */
class AdmissionAccess
{
    public const SESSION_KEY = 'admission_access';

    public const MINUTES = 120;

    public static function grant(AdmissionRegistration $registration): void
    {
        session()->put(self::SESSION_KEY, [
            'registration_id' => $registration->id,
            'expires_at' => now()->addMinutes(self::MINUTES)->timestamp,
        ]);
    }

    /**
     * The registration this session may manage, on the current site, or null.
     */
    public static function registration(): ?AdmissionRegistration
    {
        $grant = session(self::SESSION_KEY);

        if (! is_array($grant) || ! is_string($grant['registration_id'] ?? null) || ($grant['expires_at'] ?? 0) < now()->timestamp) {
            return null;
        }

        // The site scope limits this to the site being visited.
        $registration = AdmissionRegistration::query()
            ->with(['period.documentRequirements', 'documents.media'])
            ->find($grant['registration_id']);

        if ($registration !== null) {
            self::grant($registration);
        }

        return $registration;
    }

    public static function forget(): void
    {
        session()->forget(self::SESSION_KEY);
    }
}
