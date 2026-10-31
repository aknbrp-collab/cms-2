<?php

namespace App\Siakad;

use App\Models\LegalQuestion;

/**
 * Calls from the CMS to SIAKAD.
 */
interface SiakadClient
{
    /**
     * Send a moderated Tanya Hukum question to SIAKAD.
     *
     * @return array{id: string, response: array<string, mixed>, http_status: int|null}
     *
     * @throws SiakadException
     */
    public function forwardQuestion(LegalQuestion $question): array;

    /**
     * Payload the CMS sends for a question (also documented in the API spec).
     *
     * @return array<string, mixed>
     */
    public function questionPayload(LegalQuestion $question): array;
}
