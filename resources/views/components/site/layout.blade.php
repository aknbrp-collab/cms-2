@props(['site', 'seo'])

{{-- Wraps a page in the current site's theme layout (sites/{key}/layout). --}}
@include('sites.'.$site->key->value.'.layout', ['site' => $site, 'seo' => $seo, 'slot' => $slot])
