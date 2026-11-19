<?php

namespace App\Cms;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Definition of a simple admin resource: site reference content with a
 * publish switch, edited in a modal by the generic `pages::admin.resource`
 * screen. The resource key is also its permission module.
 */
abstract class AdminResource
{
    abstract public function key(): string;

    /**
     * @return class-string<Model>
     */
    abstract public function model(): string;

    abstract public function label(): string;

    abstract public function singularLabel(): string;

    abstract public function description(): string;

    /**
     * Flux icon name for the sidebar.
     */
    abstract public function icon(): string;

    /**
     * @return list<Field>
     */
    abstract public function fields(): array;

    /**
     * Attribute shown as the item title and searched in the list.
     */
    public function titleField(): string
    {
        return 'name';
    }

    /**
     * Columns searched by the list's search box.
     *
     * @return list<string>
     */
    public function searchColumns(): array
    {
        return [$this->titleField()];
    }

    /**
     * Items per page in the admin list.
     */
    public function perPage(): int
    {
        return 50;
    }

    /**
     * Secondary line under the title in the list.
     */
    public function subtitle(Model $item): ?string
    {
        return null;
    }

    /**
     * Whether a unique per-site slug is generated from the title.
     */
    public function hasSlug(): bool
    {
        return in_array('slug', (new ($this->model()))->getFillable(), true);
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function order(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy($this->titleField());
    }

    /**
     * Extra row action: [label, route name] where the route takes the item.
     *
     * @return array{0: string, 1: string}|null
     */
    public function rowAction(): ?array
    {
        return null;
    }

    /**
     * Extra button next to "New": [label, route name, Flux icon]. Shown to
     * users who may create items.
     *
     * @return array{0: string, 1: string, 2: string}|null
     */
    public function headerAction(): ?array
    {
        return null;
    }

    /**
     * Media relations to eager load for list thumbnails.
     *
     * @return list<string>
     */
    public function with(): array
    {
        return [];
    }

    /**
     * The image field shown as thumbnail in the list, if any.
     */
    public function thumbnailRelation(): ?string
    {
        return null;
    }
}
