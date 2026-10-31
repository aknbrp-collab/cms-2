<?php

namespace App\Cms\Resources;

use App\Cms\AdminResource;
use App\Cms\Field;
use App\Enums\AchievementLevel;
use App\Models\Achievement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Achievements extends AdminResource
{
    public function key(): string
    {
        return 'achievements';
    }

    public function model(): string
    {
        return Achievement::class;
    }

    public function label(): string
    {
        return __('Achievements');
    }

    public function singularLabel(): string
    {
        return __('Achievement');
    }

    public function description(): string
    {
        return __('Competition results and awards won by santri (prestasi).');
    }

    public function icon(): string
    {
        return 'star';
    }

    public function titleField(): string
    {
        return 'title';
    }

    public function fields(): array
    {
        return [
            Field::text('title', __('Achievement'), required: true, help: __('e.g. Juara 1 Pencak Silat Putra')),
            Field::text('student_name', __('Student / team'), required: true),
            Field::enum('level', __('Level'), AchievementLevel::class),
            Field::date('achieved_on', __('Date'), required: true),
            Field::text('event_name', __('Competition / event')),
            Field::image('image_id', __('Photo')),
            Field::textarea('description', __('Description')),
        ];
    }

    public function subtitle(Model $item): ?string
    {
        return $item->getAttribute('student_name').' · '.$item->getAttribute('level')?->label();
    }

    public function order(Builder $query): Builder
    {
        return $query->orderByDesc('achieved_on');
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
