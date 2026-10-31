<?php

namespace App\Cms\Resources;

use App\Cms\AdminResource;
use App\Cms\Field;
use App\Enums\OrganizationType;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Model;

class Organizations extends AdminResource
{
    public function key(): string
    {
        return 'organizations';
    }

    public function model(): string
    {
        return Organization::class;
    }

    public function label(): string
    {
        return __('Organizations');
    }

    public function singularLabel(): string
    {
        return __('Organization');
    }

    public function description(): string
    {
        return __('Student organizations: DEMA and UKM.');
    }

    public function icon(): string
    {
        return 'user-group';
    }

    public function fields(): array
    {
        return [
            Field::text('name', __('Name'), required: true),
            Field::enum('type', __('Type'), OrganizationType::class),
            Field::text('chairperson', __('Chairperson')),
            Field::text('period', __('Period'), help: __('e.g. 2026/2027')),
            Field::url('instagram_url', __('Instagram')),
            Field::number('sort_order', __('Order')),
            Field::textarea('description', __('Description')),
            Field::image('logo_id', __('Logo')),
        ];
    }

    public function subtitle(Model $item): ?string
    {
        return $item->getAttribute('type')?->label();
    }

    public function with(): array
    {
        return ['logo.media'];
    }

    public function thumbnailRelation(): ?string
    {
        return 'logo';
    }
}
