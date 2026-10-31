<?php

namespace App\Policies;

use App\Models\Publication;

class PublicationPolicy extends ContentPolicy
{
    protected function modelClass(): string
    {
        return Publication::class;
    }
}
