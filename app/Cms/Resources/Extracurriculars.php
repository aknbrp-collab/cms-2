<?php

namespace App\Cms\Resources;

use App\Cms\AdminResource;
use App\Cms\Field;
use App\Models\Extracurricular;
use Illuminate\Database\Eloquent\Model;

class Extracurriculars extends AdminResource
{
    public function key(): string
    {
        return 'extracurriculars';
    }

    public function model(): string
    {
        return Extracurricular::class;
    }

    public function label(): string
    {
        return __('Extracurriculars');
    }

    public function singularLabel(): string
    {
        return __('Extracurricular');
    }

    public function description(): string
    {
        return __('Clubs and activities outside the classroom.');
    }

    public function icon(): string
    {
        return 'trophy';
    }

    public function fields(): array
    {
        return [
            Field::text('name', __('Name'), required: true),
            Field::text('category', __('Category'), help: __('e.g. Olahraga, Seni, Bahasa')),
            Field::text('schedule', __('Schedule'), help: __('e.g. Jumat, 15.30 WIB')),
            Field::text('coach', __('Coach')),
            Field::textarea('description', __('Description')),
            Field::image('image_id', __('Photo')),
            Field::number('sort_order', __('Order')),
        ];
    }

    public function subtitle(Model $item): ?string
    {
        return $item->getAttribute('category');
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
