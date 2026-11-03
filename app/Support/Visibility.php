<?php

namespace App\Support;

use App\Concerns\HasVisibility;
use Illuminate\Database\Eloquent\Model;

/**
 * Hide an item from the public site or show it again. Callers authorize first.
 */
class Visibility
{
    /**
     * Flip the item's visibility and return whether it is now hidden.
     *
     * @param  Model  $item  a model using HasVisibility
     */
    public static function toggle(Model $item): bool
    {
        assert(in_array(HasVisibility::class, class_uses_recursive($item), true));

        $hidden = ! $item->getAttribute('is_hidden');
        $item->forceFill(['is_hidden' => $hidden])->save();

        Audit::record($hidden ? 'content.hidden' : 'content.shown', $item);

        return $hidden;
    }
}
