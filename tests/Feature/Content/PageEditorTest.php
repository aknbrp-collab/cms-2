<?php

use App\Enums\BlockType;
use App\Enums\Role;
use App\Enums\SiteKey;
use App\Models\Page;
use App\Models\Site;
use Livewire\Livewire;

beforeEach(function () {
    seedCms();
    $this->site = onSite(SiteKey::Mahad);
    $this->actingAs(cmsUser(Role::MahadAdmin));
});

test('a page is built from blocks', function () {
    Livewire::test('pages::admin.pages.edit')
        ->set('title', 'Sejarah')
        ->set('blocks.0.data.html', '<p>Didirikan tahun 1990</p><img src="x" onerror="alert(1)">')
        ->call('addBlock', BlockType::CallToAction->value)
        ->set('blocks.1.data.title', 'Daftar sekarang')
        ->set('blocks.1.data.button_url', '/pmb')
        ->call('addBlock', BlockType::Embed->value)
        ->set('blocks.2.data.url', 'https://youtu.be/dQw4w9WgXcQ')
        ->call('save')
        ->assertHasNoErrors();

    $page = Page::query()->sole();

    expect($page->site_id)->toBe($this->site->id)
        ->and($page->slug)->toBe('sejarah')
        ->and(array_column($page->blocks, 'type'))->toBe(['rich_text', 'call_to_action', 'embed'])
        ->and($page->blocks[0]['data']['html'])->not->toContain('onerror')
        ->and($page->blocks[1]['data']['button_url'])->toBe('/pmb');
});

test('blocks can be reordered and removed', function () {
    $component = Livewire::test('pages::admin.pages.edit')
        ->call('addBlock', BlockType::Image->value)
        ->call('addBlock', BlockType::Embed->value)
        ->call('moveBlock', 2, -1)
        ->call('removeBlock', 0);

    expect(array_column($component->get('blocks'), 'type'))->toBe(['embed', 'image']);
});

test('only youtube and google maps embeds are allowed', function () {
    Livewire::test('pages::admin.pages.edit')
        ->set('title', 'Lokasi')
        ->call('addBlock', BlockType::Embed->value)
        ->set('blocks.1.data.url', 'https://evil.example.com/frame')
        ->call('save')
        ->assertHasErrors('blocks.1.data.url');
});

test('unknown block fields are dropped', function () {
    Livewire::test('pages::admin.pages.edit')
        ->set('title', 'Visi Misi')
        ->set('blocks.0.data.injected', 'x')
        ->call('save')
        ->assertHasNoErrors();

    expect(Page::query()->sole()->blocks[0]['data'])->toBe(['html' => '']);
});

test('a parent must be a top level page of the same site', function () {
    $parent = Page::factory()->for($this->site)->create();
    $foreign = Page::factory()->for(Site::findByKey(SiteKey::Pondok))->create();

    Livewire::test('pages::admin.pages.edit')
        ->set('title', 'Child')
        ->set('parentId', $foreign->id)
        ->call('save')
        ->assertHasErrors('parentId')
        ->set('parentId', $parent->id)
        ->call('save')
        ->assertHasNoErrors();
});
