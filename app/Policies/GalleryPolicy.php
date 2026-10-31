<?php

namespace App\Policies;

use App\Models\Gallery;

class GalleryPolicy extends ContentPolicy
{
    protected function modelClass(): string
    {
        return Gallery::class;
    }
}
