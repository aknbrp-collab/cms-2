<?php

namespace App\Cms\Resources;

use App\Cms\AdminResource;
use App\Cms\Field;
use App\Models\Facility;

class Facilities extends AdminResource
{
    public function key(): string
    {
        return 'facilities';
    }

    public function model(): string
    {
        return Facility::class;
    }

    public function label(): string
    {
        return __('Facilities');
    }

    public function singularLabel(): string
    {
        return __('Facility');
    }

    public function description(): string
    {
        return __('Buildings and facilities (sarana prasarana) shown on the website.');
    }

    public function icon(): string
    {
        return 'building-office-2';
    }

    public function fields(): array
    {
        return [
            Field::text('name', __('Name'), required: true),
            Field::number('sort_order', __('Order')),
            Field::textarea('description', __('Description')),
            Field::image('image_id', __('Photo')),
        ];
    }

    public function with(): array
    {
        return ['image.media'];
    }

    public function thumbnailRelation(): ?string
    {
        return 'image';
    }
}
