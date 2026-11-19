<?php

use App\Enums\AchievementLevel;
use App\Enums\ContentStatus;
use App\Enums\PublicationType;
use App\Enums\Role;
use App\Enums\SiteKey;
use App\Models\Achievement;
use App\Models\Facility;
use App\Models\Journal;
use App\Models\Lecturer;
use App\Models\Organization;
use App\Models\Program;
use App\Models\Publication;
use App\Models\Site;
use App\Services\ContentWorkflow;
use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;

beforeEach(function () {
    seedCms();
    $this->withoutVite();
    $this->pondok = Site::findByKey(SiteKey::Pondok);
    $this->mahad = Site::findByKey(SiteKey::Mahad);
});

function published(Model $model): Model
{
    $model->forceFill(['is_published' => true])->save();

    return $model;
}

test('pondok pages show only published items', function () {
    published(Facility::query()->create(['site_id' => $this->pondok->id, 'name' => 'Masjid Jami']));
    Facility::query()->create(['site_id' => $this->pondok->id, 'name' => 'Gudang Rahasia']);
    published(Achievement::query()->create(['site_id' => $this->pondok->id, 'title' => 'Juara 1 MTQ', 'student_name' => 'Aisyah', 'level' => AchievementLevel::District, 'achieved_on' => now()]));

    $this->get('http://pondok.test/fasilitas')->assertOk()->assertSee('Masjid Jami')->assertDontSee('Gudang Rahasia');
    $this->get('http://pondok.test/prestasi')->assertOk()->assertSee('Juara 1 MTQ')->assertSee('Kabupaten/Kota');
    $this->get('http://pondok.test/')->assertSee('Juara 1 MTQ');
    $this->get('http://pondok.test/ekstrakurikuler')->assertOk();
});

test('site-specific pages are not served on the other site', function () {
    $this->get('http://pondok.test/dosen')->assertNotFound();
    $this->get('http://pondok.test/publikasi')->assertNotFound();
    $this->get('http://mahad.test/fasilitas')->assertNotFound();
    $this->get('http://mahad.test/prestasi')->assertNotFound();
});

test('the lecturer directory searches and shows profiles', function () {
    published(Lecturer::query()->create(['site_id' => $this->mahad->id, 'name' => 'Dr. Mahbub Ridwan', 'slug' => 'mahbub', 'expertise' => 'Hadits', 'education' => "S3 UIN\nS2 UIN"]));
    published(Lecturer::query()->create(['site_id' => $this->mahad->id, 'name' => 'Ust. Ahmad', 'slug' => 'ahmad', 'expertise' => 'Fiqh']));

    $this->get('http://mahad.test/dosen?q=hadits')->assertOk()->assertSee('Dr. Mahbub Ridwan')->assertDontSee('Ust. Ahmad');
    $this->get('http://mahad.test/dosen/mahbub')->assertOk()->assertSee('S3 UIN');
});

test('programs show their curriculum by semester', function () {
    $program = published(Program::query()->create(['site_id' => $this->mahad->id, 'name' => 'Fiqh wa Ushuluh', 'slug' => 'fiqh', 'level' => 'ula', 'core_books' => "Jam'ul Jawami'\nAl-Muhadzdzab"]));
    $program->courses()->create(['semester' => 2, 'name' => 'Ushul Fiqh II', 'credits' => 3]);
    $program->courses()->create(['semester' => 1, 'name' => 'Ushul Fiqh I', 'credits' => 2]);

    $this->get('http://mahad.test/program/fiqh')
        ->assertOk()
        ->assertSeeInOrder(['Semester 1', 'Ushul Fiqh I', 'Semester 2', 'Ushul Fiqh II'])
        ->assertSee('5 SKS')
        ->assertSee('Al-Muhadzdzab');
    $this->get('http://mahad.test/')->assertSee('Fiqh wa Ushuluh');
});

test('publications go through review and appear in the repository', function () {
    $admin = cmsUser(Role::MahadAdmin);
    $editor = cmsUser(Role::ManagingEditor);
    $journal = published(Journal::query()->create(['site_id' => $this->mahad->id, 'name' => 'Al-Ahkam', 'slug' => 'al-ahkam']));
    onSite(SiteKey::Mahad);
    $this->actingAs($admin);

    Livewire::test('pages::admin.publications.edit')
        ->set('type', PublicationType::BahtsulMasail->value)
        ->set('title', 'Hukum Akad Digital')
        ->set('authors', 'LBM Ma\'had Aly')
        ->set('journalId', $journal->id)
        ->set('volume', '12')
        ->set('issue', '1')
        ->set('doi', 'https://doi.org/10.1/x')
        ->call('save')
        ->assertHasErrors('doi')
        ->set('doi', '10.1234/ahkam.2026.1')
        ->call('save')
        ->assertHasNoErrors();

    $publication = Publication::query()->sole();
    $this->get('http://mahad.test/publikasi')->assertDontSee('Hukum Akad Digital');

    app(ContentWorkflow::class)->submit($publication, $admin);
    app(ContentWorkflow::class)->approve($publication->fresh(), $editor);

    $this->get('http://mahad.test/publikasi?jenis=bahtsul_masail')->assertOk()->assertSee('Hukum Akad Digital');
    $this->get('http://mahad.test/publikasi?jenis=thesis')->assertDontSee('Hukum Akad Digital');
    $this->get('http://mahad.test/publikasi/hukum-akad-digital')
        ->assertOk()
        ->assertSee('Al-Ahkam, Vol. 12, No. 1 ('.now()->year.')')
        ->assertSee('https://doi.org/10.1234/ahkam.2026.1', false);
    $this->get('http://mahad.test/sitemap.xml')->assertSee('http://mahad.test/publikasi/hukum-akad-digital', false);
});

test('journals show only their profile and link out to the journal website', function () {
    published(Journal::query()->create([
        'site_id' => $this->mahad->id, 'name' => 'Al-Ahkam', 'slug' => 'al-ahkam',
        'description' => 'Jurnal studi fiqh kontemporer.', 'accreditation' => 'SINTA 4',
        'issn' => '1111-2222', 'e_issn' => '3333-4444', 'url' => 'https://ojs.example.test/al-ahkam',
    ]));
    published(Journal::query()->create(['site_id' => $this->mahad->id, 'name' => 'Tanpa Situs', 'slug' => 'tanpa-situs']));

    $this->get('http://mahad.test/jurnal')
        ->assertOk()
        ->assertSee(['Al-Ahkam', 'Jurnal studi fiqh kontemporer.', 'SINTA 4', 'E-ISSN 3333-4444', 'https://ojs.example.test/al-ahkam'])
        ->assertDontSee('1111-2222')
        ->assertDontSee('http://mahad.test/jurnal/al-ahkam', false);

    // The old detail pages redirect: to the journal website, or back to the list.
    $this->get('http://mahad.test/jurnal/al-ahkam')->assertRedirect('https://ojs.example.test/al-ahkam')->assertStatus(301);
    $this->get('http://mahad.test/jurnal/tanpa-situs')->assertRedirect('http://mahad.test/jurnal');
    $this->get('http://mahad.test/sitemap.xml')->assertDontSee('/jurnal/al-ahkam', false);
});

test('organizations are listed with their type', function () {
    published(Organization::query()->create(['site_id' => $this->mahad->id, 'name' => 'Dewan Mahasantri', 'slug' => 'dema', 'type' => 'dema']));

    $this->get('http://mahad.test/organisasi')->assertOk()->assertSee('Dewan Mahasantri')->assertSee('Dewan Mahasantri (DEMA)');
});

test('publication drafts are only visible in the admin', function () {
    Publication::factory()->for($this->mahad)->create(['slug' => 'draft-x']);
    Publication::factory()->for($this->mahad)->status(ContentStatus::Published)->create(['slug' => 'terbit-x']);

    $this->get('http://mahad.test/publikasi/draft-x')->assertNotFound();
    $this->get('http://mahad.test/publikasi/terbit-x')->assertOk();
});
