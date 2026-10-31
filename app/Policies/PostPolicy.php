<?php

namespace App\Policies;

use App\Models\Post;

class PostPolicy extends ContentPolicy
{
    protected function modelClass(): string
    {
        return Post::class;
    }
}
