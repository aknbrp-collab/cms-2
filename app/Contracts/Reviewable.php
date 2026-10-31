<?php

namespace App\Contracts;

/**
 * Content that goes through the editorial workflow (see App\Concerns\HasWorkflow).
 */
interface Reviewable
{
    /**
     * Permission module of this model, e.g. "posts" for "posts.publish".
     */
    public static function permissionModule(): string;

    /**
     * Human readable name of this kind of content.
     */
    public static function contentLabel(): string;

    /**
     * Name of the admin route that edits one item (takes the model as parameter).
     */
    public static function adminEditRoute(): string;
}
