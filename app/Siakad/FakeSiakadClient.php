<?php

namespace App\Siakad;

use App\Models\LegalQuestion;
use Illuminate\Support\Str;

/**
 * Stand-in for SIAKAD during development and tests: every call succeeds.
 * Use `php artisan siakad:simulate` to send signed webhooks back to the CMS.
 */
class FakeSiakadClient extends HttpSiakadClient
{
    public function forwardQuestion(LegalQuestion $question): array
    {
        $id = 'SIAKAD-Q-'.Str::upper(Str::random(8));

        return ['id' => $id, 'response' => ['id' => $id, 'driver' => 'fake'], 'http_status' => 201];
    }
}
