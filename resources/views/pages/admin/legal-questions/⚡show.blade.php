<?php

use App\Enums\LegalQuestionStatus;
use App\Models\IntegrationLog;
use App\Models\LegalQuestion;
use App\Siakad\TanyaHukum;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Tanya Hukum question')] class extends Component {
    #[Locked]
    public string $questionId = '';

    public string $note = '';

    public string $title = '';

    public string $publicQuestion = '';

    public string $answer = '';

    public function mount(LegalQuestion $question): void
    {
        $this->authorize('legal_questions.view');
        abort_unless(Auth::user()->canAccessSite($question->site), 404);

        $this->questionId = $question->id;
        $this->fillPublication($question);
    }

    #[Computed]
    public function question(): LegalQuestion
    {
        return LegalQuestion::query()->with('moderator')->findOrFail($this->questionId);
    }

    /**
     * @return Collection<int, IntegrationLog>
     */
    #[Computed]
    public function logs(): Collection
    {
        return IntegrationLog::query()->where('subject_type', 'legal_question')->where('subject_id', $this->questionId)->latest()->limit(10)->get();
    }

    public function forward(TanyaHukum $tanyaHukum): void
    {
        $this->validate(['note' => ['nullable', 'string', 'max:2000']]);
        $tanyaHukum->forward($this->question, Auth::user(), $this->note ?: null);
        $this->done(__('Queued for SIAKAD.'));
    }

    public function reject(TanyaHukum $tanyaHukum): void
    {
        $this->validate(['note' => ['required', 'string', 'max:2000']], [], ['note' => __('reason')]);
        $tanyaHukum->reject($this->question, Auth::user(), $this->note);
        Flux::modal('reject-question')->close();
        $this->done(__('Question rejected.'));
    }

    public function publish(TanyaHukum $tanyaHukum): void
    {
        $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'publicQuestion' => ['required', 'string', 'max:10000'],
            'answer' => ['required', 'string', 'max:200000'],
        ], [], ['publicQuestion' => __('question'), 'answer' => __('answer')]);

        $tanyaHukum->publish($this->question, Auth::user(), $this->title, $this->publicQuestion, $this->answer);
        $this->done(__('Published in the Tanya Hukum archive.'));
    }

    public function unpublish(TanyaHukum $tanyaHukum): void
    {
        $tanyaHukum->unpublish($this->question, Auth::user());
        $this->done(__('Removed from the archive.'));
    }

    public function retry(TanyaHukum $tanyaHukum): void
    {
        $tanyaHukum->retryForward($this->question, Auth::user());
        $this->done(__('Retrying.'));
    }

    protected function fillPublication(LegalQuestion $question): void
    {
        $this->title = (string) ($question->title ?: Str::limit($question->question, 100, '…'));
        $this->publicQuestion = (string) ($question->public_question ?: $question->question);
        $this->answer = (string) $question->answer;
    }

    protected function done(string $message): void
    {
        unset($this->question, $this->logs);
        $this->reset('note');
        $this->fillPublication($this->question);
        Flux::toast(variant: 'success', text: $message);
    }
}; ?>

@php
    $question = $this->question;
    $canEditPublication = in_array($question->status, [LegalQuestionStatus::Answered, LegalQuestionStatus::Published], true);
@endphp

<div class="flex w-full flex-1 flex-col gap-6">
    <div>
        <flux:link :href="route('admin.legal-questions.index')" wire:navigate class="text-sm">← {{ __('Tanya Hukum') }}</flux:link>
        <flux:heading size="xl" class="mt-1">{{ $question->tracking_code }}</flux:heading>
        <flux:text class="mt-1">{{ $question->category->label() }} · {{ $question->created_at?->translatedFormat('d M Y H:i') }}</flux:text>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="min-w-0 space-y-6 lg:col-span-2">
            <flux:card class="space-y-3">
                <flux:heading>{{ __('Question as submitted') }}</flux:heading>
                <flux:text class="whitespace-pre-line text-base">{{ $question->question }}</flux:text>
            </flux:card>

            @if ($question->answer && ! $canEditPublication)
                <flux:card class="space-y-3">
                    <flux:heading>{{ __('Answer from SIAKAD') }}</flux:heading>
                    <div class="trix-content">{!! $question->answer !!}</div>
                </flux:card>
            @endif

            @if ($canEditPublication)
                <flux:card class="space-y-5">
                    <div>
                        <flux:heading>{{ __('Publication') }}</flux:heading>
                        <flux:text class="mt-1">{{ __('Edit the wording for the public archive. Remove personal details from the question.') }}</flux:text>
                    </div>
                    <flux:input wire:model="title" :label="__('Public title')" />
                    <flux:textarea wire:model="publicQuestion" :label="__('Public question')" rows="5" />
                    <x-admin.rich-text model="answer" :label="__('Answer')" />
                    <flux:text size="sm">{{ __('Answered by :name on :date', ['name' => $question->answered_by, 'date' => $question->answered_at?->translatedFormat('d M Y H:i')]) }}</flux:text>
                    @if ($question->answer_references)
                        <flux:text size="sm" class="whitespace-pre-line"><strong>{{ __('References') }}:</strong> {{ $question->answer_references }}</flux:text>
                    @endif
                    @can('legal_questions.publish')
                        <div class="flex flex-wrap gap-2">
                            <flux:button variant="primary" icon="globe-alt" wire:click="publish">{{ $question->status === LegalQuestionStatus::Published ? __('Update publication') : __('Publish') }}</flux:button>
                            @if ($question->status === LegalQuestionStatus::Published)
                                <flux:button icon="eye-slash" wire:click="unpublish" wire:confirm="{{ __('Remove this question from the public archive?') }}">{{ __('Unpublish') }}</flux:button>
                                <flux:button variant="ghost" :href="route('site.legal-questions.show', $question->slug)" target="_blank">{{ __('View on website') }} ↗</flux:button>
                            @endif
                        </div>
                    @endcan
                </flux:card>
            @endif

            <flux:card class="space-y-3">
                <flux:heading>{{ __('SIAKAD sync') }}</flux:heading>
                @forelse ($this->logs as $log)
                    <div class="flex items-center justify-between gap-3 text-sm" wire:key="log-{{ $log->id }}">
                        <span>{{ $log->action }} <flux:badge size="sm" :color="$log->status === 'success' ? 'emerald' : 'red'">{{ $log->status }}</flux:badge></span>
                        <span class="text-zinc-500">{{ $log->created_at?->translatedFormat('d M Y H:i') }}</span>
                    </div>
                    @if ($log->error)
                        <flux:text size="sm" class="text-red-600">{{ Str::limit($log->error, 200) }}</flux:text>
                    @endif
                @empty
                    <flux:text>–</flux:text>
                @endforelse
            </flux:card>
        </div>

        <div class="space-y-6">
            <flux:card class="space-y-4">
                <div class="flex items-center justify-between">
                    <flux:heading>{{ __('Status') }}</flux:heading>
                    <flux:badge size="sm" :color="$question->status->color()">{{ $question->status->label() }}</flux:badge>
                </div>

                @if ($question->status === LegalQuestionStatus::Submitted)
                    <flux:textarea wire:model="note" :label="__('Note for the scholars (optional)')" rows="3" />
                    <div class="flex flex-col gap-2">
                        @can('legal_questions.forward')
                            <flux:button variant="primary" icon="paper-airplane" wire:click="forward">{{ __('Forward to SIAKAD') }}</flux:button>
                        @endcan
                        @can('legal_questions.moderate')
                            <flux:modal.trigger name="reject-question">
                                <flux:button icon="x-mark">{{ __('Reject') }}</flux:button>
                            </flux:modal.trigger>
                        @endcan
                    </div>
                @elseif ($question->status === LegalQuestionStatus::Forwarding)
                    <flux:text>{{ __('Being sent to SIAKAD. Failed attempts are retried automatically.') }}</flux:text>
                    @can('integration_logs.retry')
                        <flux:button size="sm" icon="arrow-path" wire:click="retry">{{ __('Retry now') }}</flux:button>
                    @endcan
                @elseif ($question->status === LegalQuestionStatus::Forwarded)
                    <flux:text>{{ __('Forwarded :date. Waiting for the answer from SIAKAD.', ['date' => $question->forwarded_at?->diffForHumans()]) }}</flux:text>
                    <flux:text size="sm" class="font-mono">SIAKAD #{{ $question->external_id }}</flux:text>
                @endif

                @if ($question->moderation_note)
                    <flux:callout icon="chat-bubble-left" :heading="__('Moderator note')" :text="$question->moderation_note" />
                @endif
                <flux:error name="status" />
            </flux:card>

            <flux:card class="space-y-2 text-sm">
                <flux:heading>{{ __('Asked by') }}</flux:heading>
                @if ($question->is_anonymous)
                    <flux:badge size="sm">{{ __('Wants to stay anonymous') }}</flux:badge>
                @endif
                <flux:text>{{ $question->asker_name ?: '–' }}@if ($question->asker_city), {{ $question->asker_city }}@endif</flux:text>
                @if ($question->asker_email)
                    <flux:text><a href="mailto:{{ $question->asker_email }}" class="underline">{{ $question->asker_email }}</a></flux:text>
                @endif
                <flux:text size="sm" class="text-zinc-500">{{ __('Private: never shown on the website.') }}</flux:text>
            </flux:card>
        </div>
    </div>

    <flux:modal name="reject-question" class="md:w-96">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Reject question') }}</flux:heading>
            <flux:textarea wire:model="note" :label="__('Reason (internal)')" rows="4" />
            <div class="flex justify-end"><flux:button variant="danger" wire:click="reject">{{ __('Reject') }}</flux:button></div>
        </div>
    </flux:modal>
</div>
