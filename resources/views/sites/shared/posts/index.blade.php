<x-site.layout :site="$site" :seo="$seo">
    <x-site.page-header :title="$title" :description="$description ?? null" />

    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 md:py-14 lg:px-10">
        @if ($posts->isEmpty())
            <p class="rounded-xl bg-surface-card p-10 text-center text-on-surface-variant shadow-sm">{{ __('Belum ada tulisan.') }}</p>
        @else
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($posts as $post)
                    <x-site.post-card :post="$post" />
                @endforeach
            </div>

            <div class="mt-10">
                {{ $posts->links() }}
            </div>
        @endif
    </div>
</x-site.layout>
