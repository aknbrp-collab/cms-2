<?php

namespace App\Policies;

use App\Models\Event;

class EventPolicy extends ContentPolicy
{
    protected function modelClass(): string
    {
        return Event::class;
    }
}
