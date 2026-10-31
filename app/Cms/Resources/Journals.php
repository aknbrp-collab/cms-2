<?php

namespace App\Cms\Resources;

use App\Cms\AdminResource;
use App\Cms\Field;
use App\Models\Journal;
use Illuminate\Database\Eloquent\Model;

class Journals extends AdminResource
{
    public function key(): string
    {
        return 'journals';
    }

    public function model(): string
    {
        return Journal::class;
    }

    public function label(): string
    {
        return __('Journals');
    }

    public function singularLabel(): string
    {
        return __('Journal');
    }

    public function description(): string
    {
        return __('Scholarly journals. Articles are added under Publications.');
    }

    public function icon(): string
    {
        return 'book-open';
    }

    public function fields(): array
    {
        return [
            Field::text('name', __('Name'), required: true),
            Field::text('accreditation', __('Accreditation'), help: __('e.g. Sinta 2')),
            Field::text('issn', __('ISSN (print)'), max: 20),
            Field::text('e_issn', __('ISSN (online)'), max: 20),
            Field::url('url', __('Journal website (OJS)')),
            Field::number('sort_order', __('Order')),
            Field::textarea('description', __('Description')),
            Field::image('cover_id', __('Cover')),
        ];
    }

    public function subtitle(Model $item): ?string
    {
        return $item->getAttribute('accreditation');
    }

    public function with(): array
    {
        return ['cover.media'];
    }

    public function thumbnailRelation(): ?string
    {
        return 'cover';
    }
}
