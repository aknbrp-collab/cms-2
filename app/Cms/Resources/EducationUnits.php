<?php

namespace App\Cms\Resources;

use App\Cms\AdminResource;
use App\Cms\Field;
use App\Models\EducationUnit;
use Illuminate\Database\Eloquent\Model;

class EducationUnits extends AdminResource
{
    public function key(): string
    {
        return 'education_units';
    }

    public function model(): string
    {
        return EducationUnit::class;
    }

    public function label(): string
    {
        return __('Education units');
    }

    public function singularLabel(): string
    {
        return __('Education unit');
    }

    public function description(): string
    {
        return __('Program Pendidikan pages: student count and curriculum of each unit.');
    }

    public function icon(): string
    {
        return 'academic-cap';
    }

    public function fields(): array
    {
        return [
            Field::text('name', __('Name'), required: true, help: __('e.g. Tahfidz, PDF Wustho')),
            new Field('student_count', __('Number of students'), 'number', rules: ['integer', 'min:0', 'max:100000'], help: __('Leave 0 to hide it on the site.')),
            Field::textarea('summary', __('Summary')),
            Field::image('image_id', __('Image')),
            Field::number('sort_order', __('Order')),
            Field::richText('description', __('Description')),
        ];
    }

    public function subtitle(Model $item): ?string
    {
        $count = (int) $item->getAttribute('student_count');

        return $count > 0 ? trans_choice(':count student|:count students', $count) : null;
    }

    public function rowAction(): ?array
    {
        return [__('Curriculum'), 'admin.education-units.curriculum'];
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
