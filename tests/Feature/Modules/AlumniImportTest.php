<?php

use App\Enums\Role;
use App\Enums\SiteKey;
use App\Models\Alumnus;
use App\Models\Site;
use App\Support\AlumniImport;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

beforeEach(function () {
    seedCms();
    $this->pondok = Site::findByKey(SiteKey::Pondok);
});

function existingAlumnus(Site $site, string $name, int $year): Alumnus
{
    return Alumnus::query()->create(['site_id' => $site->id, 'name' => $name, 'graduation_year' => $year]);
}

test('pasted rows are checked: header skipped, names with commas kept, duplicates and errors flagged', function () {
    onSite(SiteKey::Pondok);
    existingAlumnus($this->pondok, 'Siti Nurhaliza', 2019);

    $rows = (new AlumniImport)->check((new AlumniImport)->fromText(implode("\n", [
        "Nama\tTahun Lulus\tKota",
        "Ahmad Fauzi, S.Pd.\t2018\tKendal",
        "siti  nurhaliza\t2019\tSemarang",
        "Hasan Basri\t2022\t",
        "Hasan Basri\t2022\tBatang",
        "Tanpa Tahun\t\tKendal",
        "\t2020\tKendal",
    ])));

    expect(array_column($rows, 'status'))->toBe(['ok', 'duplicate', 'ok', 'duplicate', 'error', 'error'])
        ->and($rows[0])->toMatchArray(['line' => 2, 'name' => 'Ahmad Fauzi, S.Pd.', 'year' => 2018, 'city' => 'Kendal'])
        ->and($rows[2]['city'])->toBe('');

    // Semicolons and unquoted commas work too.
    $semi = (new AlumniImport)->check((new AlumniImport)->fromText("Laila; 2021; Temanggung\nNur Aini;2023;Pekalongan"));
    $comma = (new AlumniImport)->check((new AlumniImport)->fromText('Dr. Ahmad, M.Pd., 2015, Kendal'));
    expect(array_column($semi, 'status'))->toBe(['ok', 'ok'])
        ->and($comma[0])->toMatchArray(['name' => 'Dr. Ahmad, M.Pd.', 'year' => 2015, 'city' => 'Kendal']);
});

test('admins preview and add pasted alumni, published when they may publish', function () {
    onSite(SiteKey::Pondok);
    existingAlumnus($this->pondok, 'Siti Nurhaliza', 2019);
    $this->actingAs(cmsUser(Role::Superadmin));

    Livewire::test('pages::admin.alumni.import')
        ->call('preview')
        ->assertHasErrors('text')
        ->set('text', "Ahmad Fauzi\t2018\tKendal\nSiti Nurhaliza\t2019\tSemarang\nTanpa Tahun\t\tKendal\nHasan Basri\t2022\tBatang")
        ->call('preview')
        ->assertHasNoErrors()
        ->assertSee(['2 rows ready', '1 duplicate', '1 row with errors', 'Publish now'])
        ->call('import')
        ->assertSet('rows', []);

    expect(Alumnus::query()->count())->toBe(3)
        ->and(Alumnus::query()->where('name', 'Ahmad Fauzi')->sole()->only(['graduation_year', 'city', 'is_published', 'site_id']))
        ->toBe(['graduation_year' => 2018, 'city' => 'Kendal', 'is_published' => true, 'site_id' => $this->pondok->id]);
});

test('pondok admins import unpublished alumni, and edited preview rows are checked again', function () {
    onSite(SiteKey::Pondok);
    $this->actingAs(cmsUser(Role::PondokAdmin));

    Livewire::test('pages::admin.alumni.import')
        ->set('text', "Ahmad Fauzi\t2018\tKendal")
        ->call('preview')
        ->assertDontSee('Publish now')
        ->assertSee('wait unpublished')
        // A tampered browser state: an invalid row marked as ready.
        ->set('rows', [
            ['line' => 1, 'name' => 'Ahmad Fauzi', 'year' => 2018, 'city' => 'Kendal', 'status' => 'ok', 'message' => ''],
            ['line' => 2, 'name' => '', 'year' => 2018, 'city' => 'X', 'status' => 'ok', 'message' => ''],
        ])
        ->set('publish', true)
        ->call('import');

    expect(Alumnus::query()->count())->toBe(1)
        ->and(Alumnus::query()->sole()->is_published)->toBeFalse();
});

test('alumni are read from an Excel file and a semicolon CSV', function () {
    onSite(SiteKey::Pondok);
    $this->actingAs(cmsUser(Role::Superadmin));

    $path = tempnam(sys_get_temp_dir(), 'alumni').'.xlsx';
    $writer = new XlsxWriter;
    $writer->openToFile($path);
    $writer->addRow(Row::fromValues(['Nama', 'Tahun Lulus', 'Kota']));
    $writer->addRow(Row::fromValues(['Muhammad Rizqi', 2020, 'Batang']));
    $writer->addRow(Row::fromValues(['Laila Fitriani', 2021, 'Temanggung']));
    $writer->close();

    Livewire::test('pages::admin.alumni.import')
        ->set('file', UploadedFile::fake()->createWithContent('alumni.xlsx', (string) file_get_contents($path)))
        ->call('preview')
        ->assertHasNoErrors()
        ->assertSee(['Muhammad Rizqi', 'Laila Fitriani', '2 rows ready'])
        ->call('import');

    Livewire::test('pages::admin.alumni.import')
        ->set('file', UploadedFile::fake()->createWithContent('alumni.csv', "\xEF\xBB\xBFNama;Tahun Lulus;Kota\nNur Aini;2023;Pekalongan\n"))
        ->call('preview')
        ->assertSee('1 row ready')
        ->call('import');

    expect(Alumnus::query()->orderBy('name')->pluck('graduation_year', 'name')->all())
        ->toBe(['Laila Fitriani' => 2021, 'Muhammad Rizqi' => 2020, 'Nur Aini' => 2023]);
});

test('the template downloads and other sites have no import', function () {
    onSite(SiteKey::Pondok);
    $this->actingAs(cmsUser(Role::PondokAdmin));

    $response = $this->get(route('admin.alumni.template'))->assertOk();
    expect($response->streamedContent())->toContain('Nama;"Tahun Lulus";Kota');

    onSite(SiteKey::Mahad);
    $this->actingAs(cmsUser(Role::Superadmin));
    Livewire::test('pages::admin.alumni.import')->assertSee('does not have an alumni list')->call('preview')->assertForbidden();

    $this->actingAs(cmsUser(Role::MahadAdmin));
    Livewire::test('pages::admin.alumni.import')->assertForbidden();
});

test('the alumni admin list is paginated, searches by city and offers bulk add', function () {
    onSite(SiteKey::Pondok);
    foreach (range(1, 55) as $i) {
        existingAlumnus($this->pondok, sprintf('Alumni %02d', $i), 2010);
    }
    Alumnus::query()->create(['site_id' => $this->pondok->id, 'name' => 'Warga Kendal', 'graduation_year' => 2024, 'city' => 'Kendal']);
    $this->actingAs(cmsUser(Role::PondokAdmin));

    Livewire::test('pages::admin.resource', ['resource' => 'alumni'])
        ->assertSee(['Bulk add', 'Warga Kendal', 'Alumni 01'])
        ->assertDontSee('Alumni 55')
        ->call('gotoPage', 2)
        ->assertSee('Alumni 55')
        ->set('search', 'kendal')
        ->assertSee('Warga Kendal')
        ->assertDontSee('Alumni 01');
});
