{{-- Documents of the registration shown in the detail dialog, with review actions. Expects $registration. --}}
@php
    use App\Enums\DocumentStatus;

    $documentsByRequirement = $registration->documents->keyBy('admission_requirement_id');
@endphp

<div class="space-y-3 border-t border-zinc-200 pt-4 dark:border-zinc-700">
    <div class="flex items-center justify-between gap-3">
        <flux:heading>{{ __('Documents') }}</flux:heading>
        @include('pages.admin.admissions.partials.document-summary', ['summary' => $registration->documentSummary()])
    </div>

    @foreach ($registration->period->documentRequirements as $requirement)
        @php $document = $documentsByRequirement->get($requirement->id); @endphp
        <div wire:key="document-{{ $requirement->id }}" class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="flex min-w-0 items-start gap-3">
                    @if ($document?->isImage())
                        <a href="{{ route('admin.admissions.document', $document) }}" target="_blank" rel="noopener" class="shrink-0">
                            <img src="{{ route('admin.admissions.document', $document) }}" alt="{{ $requirement->name }}" class="size-14 rounded object-cover ring-1 ring-zinc-200 dark:ring-zinc-700">
                        </a>
                    @elseif ($document)
                        <div class="flex size-14 shrink-0 items-center justify-center rounded bg-zinc-100 dark:bg-zinc-800"><flux:icon.document-text class="size-6 text-zinc-500" /></div>
                    @endif
                    <div class="min-w-0">
                        <div class="font-medium">{{ $requirement->name }}@if ($requirement->is_required)<span class="text-red-500"> *</span>@endif</div>
                        @if ($document)
                            <div class="text-xs text-zinc-500">
                                {{ __('Uploaded :time', ['time' => $document->uploaded_at?->translatedFormat('d M Y H:i')]) }}
                                @if ($document->reviewed_at)
                                    · {{ __('Reviewed :time', ['time' => $document->reviewed_at->translatedFormat('d M Y H:i')]) }}
                                @endif
                            </div>
                            @if ($document->status === DocumentStatus::NeedsFix && $document->note)
                                <div class="mt-1 text-xs text-amber-700 dark:text-amber-400">{{ __('Note: :note', ['note' => $document->note]) }}</div>
                            @endif
                        @else
                            <div class="text-xs text-zinc-500">{{ __('Not uploaded yet') }}</div>
                        @endif
                    </div>
                </div>

                @if ($document)
                    <div class="flex shrink-0 flex-wrap items-center gap-1">
                        <flux:badge size="sm" :color="$document->status->color()">{{ $document->status->label() }}</flux:badge>
                        <flux:button size="xs" variant="ghost" icon="arrow-down-tray" :href="route('admin.admissions.document', ['document' => $document, 'download' => 1])" :tooltip="__('Download')" />
                        @can('admissions.update')
                            @if ($document->status !== DocumentStatus::Accepted)
                                <flux:button size="xs" icon="check" wire:click="acceptDocument('{{ $document->id }}')">{{ __('Accept') }}</flux:button>
                            @endif
                            @if ($document->status !== DocumentStatus::NeedsFix)
                                <flux:button size="xs" icon="arrow-uturn-left" wire:click="startFix('{{ $document->id }}')">{{ __('Needs fixing') }}</flux:button>
                            @endif
                        @endcan
                    </div>
                @endif
            </div>

            @if ($document && $fixingDocumentId === $document->id)
                <form wire:submit="requestFix" class="mt-3 space-y-2">
                    <flux:textarea wire:model="fixNote" :label="__('What should the applicant fix?')" rows="2" :placeholder="__('e.g. The photo is blurry; upload a clear scan of all pages.')" />
                    <div class="flex justify-end gap-2">
                        <flux:button size="sm" variant="ghost" wire:click="$set('fixingDocumentId', null)">{{ __('Cancel') }}</flux:button>
                        <flux:button size="sm" type="submit" variant="primary">{{ __('Send back') }}</flux:button>
                    </div>
                </form>
            @endif
        </div>
    @endforeach
</div>
