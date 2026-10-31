<?php

namespace App\Policies;

use App\Models\Page;

class PagePolicy extends ContentPolicy
{
    protected function modelClass(): string
    {
        return Page::class;
    }
}
