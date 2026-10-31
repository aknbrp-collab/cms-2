<?php

use App\Support\Embed;
use App\Support\RichText;

test('rich text keeps formatting and strips scripts', function () {
    $html = RichText::clean('<h1>Judul</h1><p onclick="x()">Teks <strong>tebal</strong> <a href="javascript:alert(1)">x</a></p><script>alert(1)</script>');

    expect($html)
        ->toContain('<h1>Judul</h1>')
        ->toContain('<strong>tebal</strong>')
        ->not->toContain('script')
        ->not->toContain('onclick')
        ->not->toContain('javascript:');
});

test('embed links are converted to safe iframe urls', function (string $url, ?string $expected) {
    expect(Embed::toEmbedUrl($url))->toBe($expected);
})->with([
    ['https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'],
    ['https://youtu.be/dQw4w9WgXcQ', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'],
    ['https://www.youtube.com/shorts/dQw4w9WgXcQ', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'],
    ['https://www.google.com/maps/embed?pb=!1m18!1m12', 'https://www.google.com/maps/embed?pb=!1m18!1m12'],
    ['https://evil.example.com/watch?v=dQw4w9WgXcQ', null],
    ['javascript:alert(1)', null],
]);
