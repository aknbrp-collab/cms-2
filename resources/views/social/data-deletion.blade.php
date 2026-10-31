<x-legal-layout :title="__('Status Penghapusan Data')">
    <div class="box">
        <p><strong>{{ __('Kode konfirmasi') }}:</strong> {{ $code }}</p>
        <p><strong>{{ __('Status') }}:</strong> {{ __('Selesai') }} ({{ \Illuminate\Support\Carbon::parse($result['at'])->translatedFormat('d F Y H:i') }})</p>
        <p>{{ __('Data akun media sosial yang terhubung telah dihapus dari sistem (:count akun).', ['count' => $result['deleted']]) }}</p>
        <p lang="en">Your connected account data has been deleted ({{ $result['deleted'] }} account(s)).</p>
    </div>
</x-legal-layout>
