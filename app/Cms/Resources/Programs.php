<?php

namespace App\Cms\Resources;

use App\Cms\AdminResource;
use App\Cms\Field;
use App\Enums\ProgramLevel;
use App\Models\Program;
use Illuminate\Database\Eloquent\Model;

class Programs extends AdminResource
{
    public function key(): string
    {
        return 'programs';
    }

    public function model(): string
    {
        return Program::class;
    }

    public function label(): string
    {
        return __('Programs');
    }

    public function singularLabel(): string
    {
        return __('Program');
    }

    public function description(): string
    {
        return __('Takhassus study programs and their curriculum.');
    }

    public function icon(): string
    {
        return 'academic-cap';
    }

    public function fields(): array
    {
        return [
            Field::text('name', __('Name'), required: true, help: __('e.g. Fiqh wa Ushuluh')),
            Field::enum('level', __('Level'), ProgramLevel::class),
            Field::text('degree', __('Degree'), help: __('e.g. S.Ag.')),
            Field::text('accreditation', __('Accreditation'), help: __('e.g. Unggul')),
            Field::textarea('summary', __('Summary')),
            Field::textarea('core_books', __('Core books (kitab muqarrar)'), help: __('One per line.')),
            Field::image('image_id', __('Image')),
            Field::number('sort_order', __('Order')),
            Field::richText('description', __('Description')),
        ];
    }

    public function subtitle(Model $item): ?string
    {
        return $item->getAttribute('level')?->label();
    }

    public function rowAction(): ?array
    {
        return [__('Curriculum'), 'admin.programs.curriculum'];
    }
}
