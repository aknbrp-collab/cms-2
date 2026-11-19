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
        return __('Journal profiles shown on the site, each linking to its own website.');
    }

    public function icon(): string
    {
        return 'book-open';
    }

    public function fields(): array
    {
        return [
            Field::text('name', __('Name'), required: true),
            Field::text('accreditation', __('SINTA score'), help: __('e.g. SINTA 2')),
            Field::text('e_issn', __('E-ISSN'), max: 20),
            Field::url('url', __('Journal website (OJS)')),
            Field::number('sort_order', __('Order')),
            Field::textarea('description', __('About the journal')),
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
