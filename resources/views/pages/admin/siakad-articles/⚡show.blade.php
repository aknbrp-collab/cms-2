<?php

use App\Enums\SiakadArticleStatus;
use App\Models\IntegrationLog;
use App\Models\SiakadArticle;
use App\Siakad\ArticleInbox;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('SIAKAD article')] class extends Component {
    #[Locked]
    public string $articleId = '';

    public string $note = '';

    public function mount(SiakadArticle $article): void
    {
        $this->authorize('siakad_articles.view');
        abort_unless(Auth::user()->canAccessSite($article->site), 404);

        $this->articleId = $article->id;
    }

    #[Computed]
    public function article(): SiakadArticle
    {
        return SiakadArticle::query()->with(['publication', 'reviewer', 'site'])->findOrFail($this->articleId);
    }

    /**
     * @return Collection<int, IntegrationLog>
     */
    #[Computed]
    public function logs(): Collection
    {
        return IntegrationLog::query()->where('subject_type', 'siakad_article')->where('subject_id', $this->articleId)->latest()->limit(10)->get();
    }

    public function startReview(ArticleInbox $inbox): void
    {
        $inbox->startReview($this->article, Auth::user());
        $this->done(__('Review started.'));
    }

    public function approve(ArticleInbox $inbox): void
    {
        $inbox->approve($this->article, Auth::user());
        $this->done(__('Approved and published in the repository.'));
    }

    public function reject(ArticleInbox $inbox): void
    {
        $this->validate(['note' => ['required', 'string', 'max:2000']], [], ['note' => __('reason')]);

        $inbox->reject($this->article, Auth::user(), $this->note);
        Flux::modal('reject-article')->close();
        $this->done(__('Article rejected.'));
    }

    protected function done(string $message): void
    {
        unset($this->article, $this->logs);
        $this->reset('note');
        Flux::toast(variant: 'success', text: $message);
    }
}; ?>

@php($article = $this->article)

<div class="flex w-full flex-1 flex-col gap-6">
    <div>
        <flux:link :href="route('admin.siakad-articles.index')" wire:navigate class="text-sm">← {{ __('SIAKAD articles') }}</flux:link>
        <flux:heading size="xl" class="mt-1">{{ $article->title }}</flux:heading>
        <flux:text class="mt-1">{{ $article->authors }} · {{ $article->year }} · SIAKAD #{{ $article->external_id }}</flux:text>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="min-w-0 space-y-6 lg:col-span-2">
            @if ($article->updated_after_approval)
                <flux:callout icon="arrow-path" color="amber" :text="__('SIAKAD changed this article after it was approved. The website still shows the approved version until you approve again.')" />
            @endif

            <flux:card class="space-y-4">
                <flux:heading>{{ __('Abstract') }}</flux:heading>
                <flux:text class="whitespace-pre-line">{{ $article->abstract ?: '–' }}</flux:text>
                @if ($article->keywords)
                    <flux:text><strong>{{ __('Keywords') }}:</strong> {{ $article->keywords }}</flux:text>
                @endif
            </flux:card>

            @if ($article->body)
                <flux:card>
                    <flux:heading class="mb-3">{{ __('Full text') }}</flux:heading>
                    {{-- Sanitised by App\Support\RichText when received. --}}
                    <div class="trix-content">{!! $article->body !!}</div>
                </flux:card>
            @endif

            <flux:card class="space-y-3">
                <flux:heading>{{ __('Sync history') }}</flux:heading>
                @forelse ($this->logs as $log)
                    <div class="flex items-center justify-between text-sm" wire:key="log-{{ $log->id }}">
                        <span>{{ $log->action }}</span>
                        <span class="text-zinc-500">{{ $log->created_at?->translatedFormat('d M Y H:i') }}</span>
                    </div>
                @empty
                    <flux:text>–</flux:text>
                @endforelse
            </flux:card>
        </div>

        <div class="space-y-6">
            <flux:card class="space-y-4">
                <div class="flex items-center justify-between">
                    <flux:heading>{{ __('Status') }}</flux:heading>
                    <flux:badge size="sm" :color="$article->status->color()">{{ $article->status->label() }}</flux:badge>
                </div>

                @if ($article->review_note)
                    <flux:callout icon="chat-bubble-left" :heading="__('Review note')" :text="$article->review_note" />
                @endif

                <div class="flex flex-col gap-2">
                    @if (in_array($article->status, [SiakadArticleStatus::Draft, SiakadArticleStatus::Rejected], true))
                        @can('siakad_articles.review')
                            <flux:button icon="eye" wire:click="startReview">{{ __('Start review') }}</flux:button>
                        @endcan
                    @endif
                    @if (in_array($article->status, [SiakadArticleStatus::Draft, SiakadArticleStatus::UnderReview], true))
                        @can('siakad_articles.publish')
                            <flux:button variant="primary" icon="check" wire:click="approve" wire:confirm="{{ __('Approve and publish this article on the website?') }}">{{ __('Approve for website') }}</flux:button>
                            <flux:modal.trigger name="reject-article">
                                <flux:button icon="x-mark">{{ __('Reject') }}</flux:button>
                            </flux:modal.trigger>
                        @endcan
                    @endif
                </div>
                <flux:error name="status" />

                @if ($article->publication)
                    <flux:link :href="route('admin.publications.edit', $article->publication)" wire:navigate>{{ __('Open publication') }} →</flux:link>
                @endif
            </flux:card>

            <flux:card class="space-y-2 text-sm">
                <flux:text><strong>{{ __('Type') }}:</strong> {{ $article->type->label() }}</flux:text>
                <flux:text><strong>{{ __('Received') }}:</strong> {{ $article->received_at->translatedFormat('d M Y H:i') }}</flux:text>
                @if ($article->doi)
                    <flux:text><strong>DOI:</strong> {{ $article->doi }}</flux:text>
                @endif
                @if ($article->file_url)
                    <flux:link :href="$article->file_url" target="_blank">{{ __('Open file') }} ↗</flux:link>
                @endif
                @if ($article->source_url)
                    <flux:link :href="$article->source_url" target="_blank">{{ __('Open in SIAKAD') }} ↗</flux:link>
                @endif
            </flux:card>
        </div>
    </div>

    <flux:modal name="reject-article" class="md:w-96">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Reject article') }}</flux:heading>
            <flux:textarea wire:model="note" :label="__('Reason')" rows="4" />
            <div class="flex justify-end"><flux:button variant="danger" wire:click="reject">{{ __('Reject') }}</flux:button></div>
        </div>
    </flux:modal>
</div>
