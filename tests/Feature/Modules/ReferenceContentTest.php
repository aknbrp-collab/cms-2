<?php

use App\Enums\Role;
use App\Enums\SiteKey;
use App\Models\Faq;
use App\Models\Leader;
use App\Models\Site;
use Livewire\Livewire;

beforeEach(function () {
    seedCms();
    $this->withoutVite();
    $this->pondok = onSite(SiteKey::Pondok);
});

test('site admins write faq drafts and editors publish them', function () {
    $this->actingAs(cmsUser(Role::PondokAdmin));

    Livewire::test('pages::admin.faqs')
        ->call('create')
        ->set('question', 'Berapa biaya pendaftaran?')
        ->set('answer', '<p>Rp 250.000</p><script>x()</script>')
        ->set('group', 'Pendaftaran')
        ->call('save')
        ->assertHasNoErrors();

    $faq = Faq::query()->sole();
    expect($faq->is_published)->toBeFalse()->and($faq->answer)->toBe('<p>Rp 250.000</p>');

    Livewire::test('pages::admin.faqs')->call('togglePublished', $faq->id)->assertForbidden();

    $this->actingAs(cmsUser(Role::ManagingEditor));
    Livewire::test('pages::admin.faqs')->call('togglePublished', $faq->id)->assertHasNoErrors();

    expect($faq->fresh()->is_published)->toBeTrue();
});

test('site admins cannot edit published reference content', function () {
    $leader = Leader::query()->create(['site_id' => $this->pondok->id, 'name' => 'KH. A', 'position' => 'Pengasuh']);
    $leader->forceFill(['is_published' => true])->save();

    $this->actingAs(cmsUser(Role::PondokAdmin));

    Livewire::test('pages::admin.leaders')->call('edit', $leader->id)->assertForbidden();
});

test('admins of another site cannot touch reference content', function () {
    $faq = Faq::query()->create(['site_id' => $this->pondok->id, 'question' => 'Q', 'answer' => 'A']);

    $this->actingAs(cmsUser(Role::MahadAdmin));
    onSite(SiteKey::Mahad);

    Livewire::test('pages::admin.faqs')->call('edit', $faq->id)->assertNotFound();
});

test('only published faq and leaders appear on the website', function () {
    $published = Faq::query()->create(['site_id' => $this->pondok->id, 'question' => 'Terbit?', 'answer' => '<p>Ya</p>']);
    $published->forceFill(['is_published' => true])->save();
    Faq::query()->create(['site_id' => $this->pondok->id, 'question' => 'Rahasia?', 'answer' => '<p>Tidak</p>']);
    $leader = Leader::query()->create(['site_id' => $this->pondok->id, 'name' => 'KH. Ahmad', 'position' => 'Pengasuh']);
    $leader->forceFill(['is_published' => true])->save();

    $this->get('http://pondok.test/faq')->assertOk()->assertSee('Terbit?')->assertDontSee('Rahasia?');
    $this->get('http://pondok.test/pimpinan')->assertOk()->assertSee('KH. Ahmad');
});

test('module pages return 404 on sites without the module', function () {
    config(['cms.sites.mahad.modules' => ['pages', 'posts']]);

    $this->get('http://mahad.test/faq')->assertNotFound();
    $this->get('http://mahad.test/agenda')->assertNotFound();
    $this->get('http://mahad.test/penerimaan')->assertNotFound();
    expect(Site::findByKey(SiteKey::Mahad)->hasModule('faqs'))->toBeFalse();
});
