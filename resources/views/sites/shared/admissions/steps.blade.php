{{-- Step indicator of online admission. Expects $current (1 = biodata, 2 = berkas). --}}
<ol class="mb-8 flex items-center gap-3 text-sm" aria-label="{{ __('Langkah pendaftaran') }}">
    @foreach ([1 => __('Data diri'), 2 => __('Unggah berkas')] as $number => $name)
        <li class="flex items-center gap-3" @if ($number === $current) aria-current="step" @endif>
            @if ($number > 1)
                <span class="h-px w-8 bg-border-subtle sm:w-16" aria-hidden="true"></span>
            @endif
            <span @class([
                'flex size-8 shrink-0 items-center justify-center rounded-full font-bold',
                'bg-primary text-on-primary' => $number === $current,
                'bg-mint text-primary' => $number < $current,
                'bg-surface-container text-muted' => $number > $current,
            ])>{{ $number }}</span>
            <span @class(['font-semibold text-on-surface' => $number === $current, 'text-on-surface-variant' => $number !== $current])>{{ $name }}</span>
        </li>
    @endforeach
</ol>
