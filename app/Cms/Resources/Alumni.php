<?php

namespace App\Cms\Resources;

use App\Cms\AdminResource;
use App\Cms\Field;
use App\Models\Alumnus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Alumni extends AdminResource
{
    public function key(): string
    {
        return 'alumni';
    }

    public function model(): string
    {
        return Alumnus::class;
    }

    public function label(): string
    {
        return __('Alumni');
    }

    public function singularLabel(): string
    {
        return __('Alumnus');
    }

    public function description(): string
    {
        return __('Alumni directory on the site: name, graduation year and city.');
    }

    public function icon(): string
    {
        return 'user-group';
    }

    public function fields(): array
    {
        return [
            Field::text('name', __('Name'), required: true),
            new Field('graduation_year', __('Graduation year'), 'number', required: true, rules: ['integer', 'min:1900', 'max:2100']),
            Field::text('city', __('City'), max: 120),
        ];
    }

    public function subtitle(Model $item): ?string
    {
        return collect([$item->getAttribute('graduation_year'), $item->getAttribute('city')])->filter()->implode(' · ');
    }

    public function order(Builder $query): Builder
    {
        return $query->orderByDesc('graduation_year')->orderBy('name');
    }
}
