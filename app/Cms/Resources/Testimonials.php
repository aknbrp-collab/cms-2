<?php

namespace App\Cms\Resources;

use App\Cms\AdminResource;
use App\Cms\Field;
use App\Models\Testimonial;
use Illuminate\Database\Eloquent\Model;

class Testimonials extends AdminResource
{
    public function key(): string
    {
        return 'testimonials';
    }

    public function model(): string
    {
        return Testimonial::class;
    }

    public function label(): string
    {
        return __('Testimonials');
    }

    public function singularLabel(): string
    {
        return __('Testimonial');
    }

    public function description(): string
    {
        return __('Alumni testimonials shown on the site.');
    }

    public function icon(): string
    {
        return 'chat-bubble-left-right';
    }

    public function fields(): array
    {
        return [
            Field::text('name', __('Name'), required: true),
            new Field('graduation_year', __('Graduation year'), 'number', rules: ['integer', 'min:0', 'max:2100'], help: __('Leave 0 if unknown.')),
            Field::text('occupation', __('Current role or institution'), help: __('e.g. Mahasiswa Universitas Al-Azhar Kairo')),
            Field::image('photo_id', __('Photo')),
            Field::number('sort_order', __('Order')),
            new Field('quote', __('Testimonial'), 'textarea', required: true, rules: ['string', 'max:2000']),
        ];
    }

    public function subtitle(Model $item): ?string
    {
        return $item->getAttribute('occupation');
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
