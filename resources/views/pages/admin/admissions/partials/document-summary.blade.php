{{-- Upload progress of one registration. Expects $summary from AdmissionRegistration::documentSummary(). --}}
@if ($summary['total'] === 0)
    <span class="text-zinc-400">–</span>
@else
    <div class="flex flex-wrap items-center gap-1">
        <flux:badge size="sm" :color="$summary['missing_required'] > 0 ? 'zinc' : 'emerald'">{{ $summary['uploaded'] }}/{{ $summary['total'] }}</flux:badge>
        @if ($summary['needs_fix'] > 0)
            <flux:badge size="sm" color="amber" icon="exclamation-triangle">{{ trans_choice(':count to fix|:count to fix', $summary['needs_fix']) }}</flux:badge>
        @elseif ($summary['accepted'] === $summary['total'])
            <flux:badge size="sm" color="emerald" icon="check">{{ __('All accepted') }}</flux:badge>
        @endif
    </div>
@endif
