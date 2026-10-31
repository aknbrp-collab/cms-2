<?php

namespace App\Cms\Resources;

use App\Cms\AdminResource;
use App\Cms\Field;
use App\Models\Lecturer;
use Illuminate\Database\Eloquent\Model;

class Lecturers extends AdminResource
{
    public function key(): string
    {
        return 'lecturers';
    }

    public function model(): string
    {
        return Lecturer::class;
    }

    public function label(): string
    {
        return __('Lecturers');
    }

    public function singularLabel(): string
    {
        return __('Lecturer');
    }

    public function description(): string
    {
        return __('Directory of lecturers and asatidzah (Dewan Masyayikh).');
    }

    public function icon(): string
    {
        return 'identification';
    }

    public function fields(): array
    {
        return [
            Field::text('name', __('Name with titles'), required: true),
            Field::text('position', __('Position'), help: __('e.g. Dosen Tetap, Rais Masyayikh')),
            Field::text('expertise', __('Expertise / sanad')),
            Field::text('nidn', __('NIDN / NUPTK'), max: 20),
            Field::email('email', __('Email')),
            Field::number('sort_order', __('Order')),
            Field::textarea('education', __('Education'), help: __('One degree per line.')),
            Field::image('photo_id', __('Photo')),
            Field::richText('bio', __('Biography')),
        ];
    }

    public function subtitle(Model $item): ?string
    {
        return $item->getAttribute('position');
    }

    public function with(): array
    {
        return ['photo.media'];
    }

    public function thumbnailRelation(): ?string
    {
        return 'photo';
    }
}
