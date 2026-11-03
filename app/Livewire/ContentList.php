<?php

namespace App\Livewire;

use App\Support\Visibility;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Base for workflow content list screens: pagination, the hide/show action
 * and the visibility filter. The model uses HasVisibility and its policy
 * has a "hide" ability.
 */
abstract class ContentList extends Component
{
    use WithPagination;

    /** '' (all), 'visible' or 'hidden'. */
    #[Url]
    public string $visibility = '';

    /**
     * @return class-string<Model>
     */
    abstract protected function visibilityModel(): string;

    public function toggleHidden(string $id): void
    {
        $item = $this->visibilityModel()::query()->findOrFail($id);
        $this->authorize('hide', $item);

        $hidden = Visibility::toggle($item);

        Flux::toast(variant: 'success', text: $hidden ? __('Hidden from the website.') : __('Shown on the website again.'));
    }

    public function updatedVisibility(): void
    {
        $this->resetPage();
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function applyVisibilityFilter(Builder $query): Builder
    {
        return match ($this->visibility) {
            'visible' => $query->where($query->qualifyColumn('is_hidden'), false),
            'hidden' => $query->where($query->qualifyColumn('is_hidden'), true),
            default => $query,
        };
    }
}
