<?php

namespace Database\Seeders;

use App\Enums\AchievementLevel;
use App\Enums\BlockType;
use App\Enums\ContentStatus;
use App\Enums\GalleryType;
use App\Enums\LegalCategory;
use App\Enums\LegalQuestionStatus;
use App\Enums\MenuItemType;
use App\Enums\MenuLocation;
use App\Enums\OrganizationType;
use App\Enums\PageTemplate;
use App\Enums\PostType;
use App\Enums\ProgramLevel;
use App\Enums\PublicationType;
use App\Enums\SiteKey;
use App\Models\Achievement;
use App\Models\AdmissionPeriod;
use App\Models\Alumnus;
use App\Models\Category;
use App\Models\Event;
use App\Models\Extracurricular;
use App\Models\Facility;
use App\Models\Faq;
use App\Models\Gallery;
use App\Models\Journal;
use App\Models\Leader;
use App\Models\Lecturer;
use App\Models\LegalQuestion;
use App\Models\Menu;
use App\Models\Organization;
use App\Models\Page;
use App\Models\Post;
use App\Models\Program;
use App\Models\Publication;
use App\Models\SiakadArticle;
use App\Models\Site;
use App\Models\Testimonial;
use App\Models\User;
use App\Support\CurrentSite;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Example content for local development and demos (not for production).
 *
 * Run with: php artisan db:seed --class=DemoContentSeeder
 */
class DemoContentSeeder extends Seeder
{
    public function run(): void
    {
        $author = User::query()->first();

        foreach (SiteKey::cases() as $key) {
            $site = Site::findByKey($key);

            if ($site === null || Post::withoutSiteScope()->where('site_id', $site->id)->exists()) {
                continue;
            }

            app(CurrentSite::class)->using($site, fn () => $key === SiteKey::Pondok
                ? $this->pondok($site, $author)
                : $this->mahad($site, $author));
        }
    }

    protected function pondok(Site $site, ?User $author): void
    {
        $site->fillSettings([
            ...$site->allSettings(),
            'identity.tagline' => 'Pesantren Modern Berbasis Salaf',
            'identity.description' => 'Lembaga pendidikan Islam yang mendidik santri berakhlakul karimah, berilmu amaliah, dan beramal ilmiah.',
            'contact.phone' => '0294-452-473',
            'contact.email' => 'info@darulamanah.test',
            'contact.whatsapp' => '6281234567890',
            'contact.address' => 'Jl. Sukorejo - Tersono KM 04, Sukorejo, Kendal, Jawa Tengah',
            'social.youtube' => 'https://youtube.com/@darulamanah',
            'social.instagram' => 'https://instagram.com/darulamanah',
            'social.facebook' => 'https://facebook.com/darulamanah',
            'header.announcement' => 'Penerimaan Santri Baru (PSB) Tahun Ajaran 2026/2027 telah dibuka',
            'header.cta_label' => 'Daftar PSB',
            'header.cta_url' => '/penerimaan',
        ])->save();

        $categories = $this->categories($site, ['Keislaman' => true, 'Prestasi Santri' => true, 'Kegiatan Pesantren' => false]);

        $this->posts($site, $author, $categories, [
            ['Sosialisasi Anti-Bullying Bersama Kapolsek Sukorejo', 'Kegiatan Pesantren', true],
            ['Imam Masjid Al-Azhar Kairo Sampaikan Tausiyah di Darul Amanah', 'Keislaman'],
            ['Atlet Pencak Silat Raih Juara Umum Sunan Kalijaga Cup', 'Prestasi Santri'],
            ['Keutamaan Menuntut Ilmu Menurut Kitab Ta\'limul Muta\'allim', 'Keislaman'],
            ['Santri Putri Juara MTQ Tingkat Kabupaten', 'Prestasi Santri'],
            ['Haflah Akhirussanah dan Wisuda Tahfidz 30 Juz', 'Kegiatan Pesantren'],
            ['Adab Santri kepada Guru dan Kitab', 'Keislaman'],
            ['Tim Robotik Santri Lolos Final Nasional', 'Prestasi Santri'],
        ]);
        $this->announcements($site, $author, [
            'Jadwal Kepulangan Santri Libur Semester Ganjil',
            'Pembukaan PSB Gelombang II',
            'Jadwal Ujian Akhir Semester',
        ]);

        $pages = $this->pages($site, $author, [
            'profil' => ['Profil', null, PageTemplate::Default],
            'sejarah' => ['Sejarah', 'profil', PageTemplate::Default],
            'visi-misi' => ['Visi & Misi', 'profil', PageTemplate::Default],
            'profil-pimpinan' => ['Profil Pimpinan', 'profil', PageTemplate::Default],
            'pendaftaran' => ['Pendaftaran Santri Baru', null, PageTemplate::Default],
            'kontak' => ['Hubungi Kami', null, PageTemplate::Contact],
        ]);

        $this->pondokModules($site);

        $this->modules($site, $author, 'PSB', [
            ['Pengasuh Pondok Pesantren', 'KH. Ahmad Mudzakir', 'Sanad Tahfidz & Kitab Kuning'],
            ['Kepala Madrasah', 'Ust. H. Muhammad Ridwan, M.Pd.I', 'Manajemen Pendidikan Islam'],
        ], ['Reguler', 'Tahfidz Al-Qur\'an', 'Bahasa Arab & Inggris']);

        $this->menu($site, MenuLocation::Header, [
            ['Profil', null, [['Sejarah', $pages['sejarah']], ['Visi & Misi', $pages['visi-misi']], ['Pimpinan', '/pimpinan'], ['Sarana Prasarana', '/fasilitas'], ['Ekstrakurikuler', '/ekstrakurikuler'], ['Prestasi', '/prestasi']]],
            ['Berita', null, [['Berita', '/berita'], ['Pengumuman', '/pengumuman'], ['Agenda', '/agenda']]],
            ['Pendaftaran', null, [['Info PSB', '/penerimaan'], ['Pendaftaran Online', '/penerimaan/daftar'], ['FAQ', '/faq']]],
            ['Galeri', null, [['Foto', '/galeri?jenis=photo'], ['Video', '/galeri?jenis=video']]],
            ['Kontak', $pages['kontak']],
        ]);
        $this->call([ProgramPendidikanSeeder::class, AlumniMenuSeeder::class]);

        $this->menu($site, MenuLocation::Footer, [
            ['Pengumuman', '/pengumuman'],
            ['Profil Pimpinan', $pages['profil-pimpinan']],
            ['Pendaftaran PSB', '/penerimaan'],
            ['Prestasi Santri', $categories['Prestasi Santri']],
        ]);
    }

    protected function mahad(Site $site, ?User $author): void
    {
        $site->fillSettings([
            ...$site->allSettings(),
            'identity.tagline' => 'Pendidikan Tinggi Pesantren • Kementerian Agama RI',
            'identity.description' => 'Pendidikan tinggi pesantren jenjang Marhalah Ula (S1) dan Marhalah Tsaniyah (S2) berbasis turats dengan sanad keilmuan yang bersambung.',
            'contact.phone' => '(021) 7890-4321',
            'contact.email' => 'pmb@mahadaly.test',
            'contact.whatsapp' => '6281234567890',
            'contact.address' => 'Jl. Pesantren Luhur No. 1, Kompleks Kampus Putra-Putri',
            'header.announcement' => 'Penerimaan Mahasantri Baru 2026/2027',
            'header.cta_label' => 'PMB 2026/2027',
            'header.cta_url' => '/penerimaan',
            'prayer.enabled' => true,
            'prayer.latitude' => -7.0,
            'prayer.longitude' => 110.0,
            'prayer.method' => 'kemenag',
        ])->save();

        $categories = $this->categories($site, ['Kajian Ilmiah' => true, 'Bahtsul Masail' => true, 'Kegiatan Kampus' => false]);

        $this->posts($site, $author, $categories, [
            ['Simposium Nasional Turats: Kontekstualisasi Fiqh Lingkungan', 'Kegiatan Kampus', true],
            ['Metodologi Qiyas dalam Jam\'ul Jawami\'', 'Kajian Ilmiah'],
            ['Hasil Bahtsul Masail: Akad Digital dalam Tinjauan Fiqh', 'Bahtsul Masail'],
            ['Imtihan Syafahi Kitab Kuning Semester Genap', 'Kegiatan Kampus'],
            ['Takhrij Hadits dengan Pendekatan Digital', 'Kajian Ilmiah'],
            ['Hukum Zakat Profesi Menurut Empat Madzhab', 'Bahtsul Masail'],
        ]);
        $this->announcements($site, $author, [
            'Jadwal Munaqasyah Risalah Marhalah Tsaniyah',
            'Pendaftaran Beasiswa Mahasantri Berprestasi',
        ]);

        $pages = $this->pages($site, $author, [
            'tentang-kami' => ['Tentang Kami', null, PageTemplate::Default],
            'sejarah-dan-sanad' => ['Sejarah & Sanad Keilmuan', 'tentang-kami', PageTemplate::Default],
            'visi-dan-misi' => ['Visi & Misi', 'tentang-kami', PageTemplate::Default],
            'pmb' => ['Penerimaan Mahasantri Baru', null, PageTemplate::Default],
            'kontak' => ['Kontak', null, PageTemplate::Contact],
        ]);

        $this->mahadModules($site, $author);
        $this->tanyaHukum($site);

        $this->modules($site, $author, 'PMB', [
            ['Rais Masyayikh', 'Syaikh Dr. KH. Luqmanul Hakim, MA.', 'Sanad Ushul Fiqh Al-Azhar Kairo'],
            ['Mudir Ma\'had Aly', 'KH. M. Syukron Ma\'mun, Lc., M.Hum.', 'Tahqiq Turats Fiqh Syafi\'i'],
            ['Wakil Mudir Bidang Akademik', 'Dr. H. Mahbub Ridwan, M.Ag.', 'Hadits wa \'Ulumuhu'],
        ], ['Marhalah Ula (S1) Fiqh wa Ushuluh', 'Marhalah Ula (S1) Hadits wa \'Ulumuhu', 'Marhalah Tsaniyah (S2) Fiqh Mu\'amalah']);

        $this->menu($site, MenuLocation::Header, [
            ['Profil', null, [['Tentang Kami', $pages['tentang-kami']], ['Visi & Misi', $pages['visi-dan-misi']], ['Sejarah & Sanad', $pages['sejarah-dan-sanad']], ['Pimpinan', '/pimpinan']]],
            ['Akademik', null, [['Program Takhassus', '/program'], ['Direktori Dosen', '/dosen'], ['Agenda & Halaqah', '/agenda']]],
            ['Publikasi & Riset', null, [['Repositori', '/publikasi'], ['Bahtsul Masail', '/publikasi?jenis=bahtsul_masail'], ['Jurnal Ilmiah', '/jurnal'], ['Tanya Hukum', '/tanya-hukum']]],
            ['Mahasantri', null, [['Organisasi (DEMA & UKM)', '/organisasi'], ['Galeri', '/galeri']]],
            ['Berita', '/berita'],
            ['PMB', null, [['Informasi PMB', '/penerimaan'], ['FAQ', '/faq']]],
            ['Kontak', $pages['kontak']],
        ]);
        $this->menu($site, MenuLocation::TopBar, [
            ['Maktabah Digital', '#'],
            ['Portal Siamad SSO', '#'],
            ['Jurnal OJS', '#'],
        ]);
        $this->menu($site, MenuLocation::Footer, [
            ['Penerimaan Mahasantri Baru', '/penerimaan'],
            ['Berita Akademik', '/berita'],
            ['Pengumuman', '/pengumuman'],
        ]);
    }

    /**
     * Facilities, extracurriculars and achievements of the Pondok.
     */
    protected function pondokModules(Site $site): void
    {
        $this->reference(Facility::class, $site, [
            ['name' => 'Masjid Jami\'', 'description' => 'Pusat ibadah dan kajian kitab santri putra dan putri.'],
            ['name' => 'Asrama Santri', 'description' => 'Asrama putra dan putri dengan pembina di setiap kamar.'],
            ['name' => 'Perpustakaan', 'description' => 'Koleksi kitab kuning, buku umum, dan ruang baca yang nyaman.'],
            ['name' => 'Laboratorium Komputer', 'description' => 'Fasilitas pembelajaran teknologi informasi.'],
        ]);

        $this->reference(Extracurricular::class, $site, [
            ['name' => 'Pencak Silat', 'category' => 'Olahraga', 'schedule' => 'Jumat, 15.30 WIB', 'coach' => 'Ust. Hamid', 'description' => 'Bela diri tradisional untuk membentuk fisik dan mental santri.'],
            ['name' => 'Hadrah & Sholawat', 'category' => 'Seni', 'schedule' => 'Kamis malam', 'description' => 'Grup rebana yang sering tampil di acara pesantren.'],
            ['name' => 'Muhadharah', 'category' => 'Bahasa', 'schedule' => 'Ahad pagi', 'description' => 'Latihan pidato tiga bahasa: Indonesia, Arab, Inggris.'],
            ['name' => 'Pramuka', 'category' => 'Kepanduan', 'schedule' => 'Sabtu, 14.00 WIB', 'description' => 'Pembinaan kepemimpinan dan kemandirian.'],
        ]);

        $this->reference(Achievement::class, $site, [
            ['title' => 'Juara Umum Pencak Silat', 'student_name' => 'Tim Pencak Silat', 'level' => AchievementLevel::Province, 'event_name' => 'Sunan Kalijaga Cup V', 'achieved_on' => now()->subWeeks(2)],
            ['title' => 'Juara 1 MTQ Putri', 'student_name' => 'Siti Aisyah', 'level' => AchievementLevel::District, 'event_name' => 'MTQ Kabupaten Kendal', 'achieved_on' => now()->subMonth()],
            ['title' => 'Medali Perak Olimpiade Sains', 'student_name' => 'Muhammad Faiz', 'level' => AchievementLevel::National, 'event_name' => 'OSN Pesantren', 'achieved_on' => now()->subMonths(2)],
            ['title' => 'Juara 2 Debat Bahasa Arab', 'student_name' => 'Tim Debat', 'level' => AchievementLevel::Province, 'event_name' => 'Festival Bahasa Arab Jateng', 'achieved_on' => now()->subMonths(3)],
        ]);

        $this->reference(Alumnus::class, $site, [
            ['name' => 'Ahmad Fauzi', 'graduation_year' => 2018, 'city' => 'Kendal'],
            ['name' => 'Siti Nurhaliza', 'graduation_year' => 2018, 'city' => 'Semarang'],
            ['name' => 'Muhammad Rizqi', 'graduation_year' => 2020, 'city' => 'Batang'],
            ['name' => 'Laila Fitriani', 'graduation_year' => 2021, 'city' => 'Temanggung'],
            ['name' => 'Hasan Basri', 'graduation_year' => 2022, 'city' => 'Kendal'],
            ['name' => 'Nur Aini', 'graduation_year' => 2023, 'city' => 'Pekalongan'],
        ]);

        $this->reference(Testimonial::class, $site, [
            ['name' => 'Muhammad Rizqi', 'graduation_year' => 2020, 'occupation' => 'Mahasiswa Universitas Al-Azhar Kairo', 'quote' => 'Bekal kitab kuning dan bahasa Arab di pondok sangat membantu saya kuliah di Kairo.'],
            ['name' => 'Laila Fitriani', 'graduation_year' => 2021, 'occupation' => 'Guru MI di Temanggung', 'quote' => 'Disiplin dan adab yang ditanamkan para asatidz terbawa sampai sekarang saat saya mengajar.'],
        ]);
    }

    /**
     * Programs with curriculum, lecturers, a journal, publications and organizations.
     */
    protected function mahadModules(Site $site, ?User $author): void
    {
        $programs = [
            ['Fiqh wa Ushuluh', ProgramLevel::Ula, 'S.Ag.', 'Unggul', 'Penguasaan metodologi istinbath hukum, qawa\'id fiqhiyyah, dan perbandingan madzhab.', "Jam'ul Jawami'\nAl-Muhadzdzab\nNihayatul Muhtaj"],
            ['Hadits wa \'Ulumuhu', ProgramLevel::Ula, 'S.Ag.', 'A', 'Kajian kritik sanad dan matan, takhrij, ilmu rijal, serta kutubus sittah.', "Fathul Bari\nTadribur Rawi\nAl-Muwaththa'"],
            ['Fiqh Mu\'amalah', ProgramLevel::Tsaniyah, 'M.Ag.', 'Unggul', 'Fiqh ekonomi dan transaksi kontemporer berbasis turats.', "Al-Asybah wan Nadha'ir\nI'anatut Thalibin"],
        ];

        foreach ($programs as $order => [$name, $level, $degree, $accreditation, $summary, $books]) {
            $program = Program::query()->create([
                'site_id' => $site->id, 'name' => $name, 'slug' => Str::slug($name), 'level' => $level, 'degree' => $degree,
                'accreditation' => $accreditation, 'summary' => $summary, 'core_books' => $books, 'sort_order' => $order,
                'description' => '<p>'.e($summary).' Perkuliahan memadukan sistem halaqah dan kurikulum pendidikan tinggi.</p>',
            ]);
            $program->forceFill(['is_published' => true])->save();

            foreach ([[1, 'Ushul Fiqh I', 'Al-Waraqat'], [1, 'Qawa\'id Fiqhiyyah', 'Al-Faraid Al-Bahiyyah'], [2, 'Ushul Fiqh II', "Jam'ul Jawami'"], [2, 'Fiqh Ibadah', 'Fathul Mu\'in']] as $index => [$semester, $course, $kitab]) {
                $program->courses()->create(['semester' => $semester, 'code' => 'MK'.($order + 1).'0'.($index + 1), 'name' => $course, 'credits' => 2, 'books' => $kitab, 'sort_order' => $index]);
            }
        }

        $this->reference(Lecturer::class, $site, [
            ['name' => 'Syaikh Dr. KH. Luqmanul Hakim, MA.', 'slug' => 'luqmanul-hakim', 'position' => 'Rais Masyayikh', 'expertise' => 'Ushul Fiqh Komparatif', 'education' => "S3 Universitas Al-Azhar Kairo\nS2 Universitas Al-Azhar Kairo"],
            ['name' => 'KH. M. Syukron Ma\'mun, Lc., M.Hum.', 'slug' => 'syukron-mamun', 'position' => 'Mudir', 'expertise' => 'Tahqiq Turats Fiqh Syafi\'i', 'education' => "S2 UIN Sunan Kalijaga\nS1 Universitas Al-Azhar Kairo"],
            ['name' => 'Dr. H. Mahbub Ridwan, M.Ag.', 'slug' => 'mahbub-ridwan', 'position' => 'Dosen Tetap', 'expertise' => 'Hadits wa \'Ulumuhu', 'education' => "S3 UIN Walisongo\nS2 UIN Walisongo"],
            ['name' => 'Ust. Ahmad Syarifuddin, M.H.', 'slug' => 'ahmad-syarifuddin', 'position' => 'Dosen Tetap', 'expertise' => 'Fiqh Mu\'amalah', 'education' => 'S2 UIN Walisongo'],
        ]);

        $journal = Journal::query()->create([
            'site_id' => $site->id, 'name' => 'Al-Ahkam: Jurnal Studi Fiqh Kontemporer', 'slug' => 'al-ahkam', 'issn' => '2580-992X',
            'accreditation' => 'Sinta 2', 'description' => 'Jurnal kajian fiqh kontemporer dan turats Nusantara.',
        ]);
        $journal->forceFill(['is_published' => true])->save();

        $publications = [
            [PublicationType::BahtsulMasail, 'Hukum Akad Kontrak Pintar (Smart Contract) dalam Tinjauan Ijarah dan Wakalah', 'Lembaga Bahtsul Masail Ma\'had Aly', null],
            [PublicationType::JournalArticle, 'Metodologi Qiyas Kausalitas dalam Jam\'ul Jawami\'', 'Mahbub Ridwan', $journal],
            [PublicationType::Research, 'Fiqh Lingkungan dalam Perspektif Maqashid Syariah', 'Ahmad Syarifuddin', null],
            [PublicationType::Thesis, 'Tahqiq Naskah Fiqh Syafi\'i dari Pesisir Utara Jawa', 'Abdul Karim', null],
        ];

        foreach ($publications as $index => [$type, $title, $authors, $inJournal]) {
            $this->publish(Publication::query()->make([
                'site_id' => $site->id, 'author_id' => $author?->id, 'type' => $type, 'title' => $title, 'slug' => Str::slug($title),
                'authors' => $authors, 'year' => (int) now()->format('Y') - ($index % 2), 'journal_id' => $inJournal?->id,
                'volume' => $inJournal ? '12' : null, 'issue' => $inJournal ? '1' : null, 'keywords' => 'fiqh, turats, maqashid',
                'abstract' => "Kajian ini membahas {$title} dengan pendekatan turats dan konteks kontemporer.",
            ]), now()->subDays($index + 1));
        }

        $this->reference(Organization::class, $site, [
            ['name' => 'Dewan Mahasantri (DEMA)', 'slug' => 'dema', 'type' => OrganizationType::Dema, 'chairperson' => 'Muhammad Rizqi', 'period' => '2026/2027', 'description' => 'Organisasi perwakilan mahasantri Ma\'had Aly.'],
            ['name' => 'Lembaga Bahtsul Masail Mahasantri', 'slug' => 'lbm', 'type' => OrganizationType::Ukm, 'description' => 'Forum musyawarah ilmiah membahas persoalan keagamaan aktual.'],
            ['name' => 'Jam\'iyyah Qurra\' wa Huffadz', 'slug' => 'jqh', 'type' => OrganizationType::Ukm, 'description' => 'Pembinaan tilawah dan tahfidz Al-Qur\'an.'],
        ]);
    }

    /**
     * Published Tanya Hukum answers, one new question and one incoming SIAKAD article.
     */
    protected function tanyaHukum(Site $site): void
    {
        $answered = [
            [LegalCategory::Ibadah, 'Hukum Menjamak Shalat bagi Musafir', 'Bolehkah menjamak shalat Dzuhur dan Ashar ketika dalam perjalanan kurang dari dua hari?'],
            [LegalCategory::Muamalah, 'Jual Beli dengan Sistem Pre-Order', 'Bagaimana hukum jual beli pre-order yang barangnya belum ada saat akad?'],
        ];

        foreach ($answered as [$category, $title, $question]) {
            LegalQuestion::query()->create([
                'site_id' => $site->id, 'category' => $category, 'asker_name' => 'Hamba Allah', 'is_anonymous' => true, 'question' => $question,
            ])->forceFill([
                'status' => LegalQuestionStatus::Published, 'title' => $title, 'slug' => Str::slug($title), 'public_question' => $question,
                'external_id' => 'SIAKAD-Q-'.Str::upper(Str::random(6)), 'forwarded_at' => now()->subDays(10),
                'answer' => '<p>Para ulama madzhab Syafi\'i membolehkannya dengan syarat-syarat tertentu. Wallahu a\'lam.</p>',
                'answered_by' => 'Dewan Asatidz Ma\'had Aly', 'answer_references' => "Fathul Mu'in\nI'anatut Thalibin",
                'answered_at' => now()->subDays(5), 'published_at' => now()->subDays(4),
            ])->save();
        }

        LegalQuestion::query()->create([
            'site_id' => $site->id, 'category' => LegalCategory::Munakahat, 'asker_name' => 'Fulan', 'asker_city' => 'Kendal',
            'question' => 'Apakah wali hakim boleh menikahkan jika wali nasab berada di luar negeri dan sulit dihubungi?',
        ]);

        SiakadArticle::query()->create([
            'site_id' => $site->id, 'external_id' => 'ART-DEMO-001', 'type' => PublicationType::Research,
            'title' => 'Zakat Profesi dalam Perspektif Empat Madzhab', 'authors' => 'Ust. Ahmad Syarifuddin, M.H.',
            'abstract' => 'Artikel kiriman SIAKAD yang menunggu persetujuan untuk diterbitkan di website.',
            'year' => (int) now()->format('Y'), 'payload' => ['id' => 'ART-DEMO-001', 'demo' => true], 'received_at' => now(),
        ]);
    }

    /**
     * Create published reference items of a model.
     *
     * @param  class-string<Model>  $modelClass
     * @param  list<array<string, mixed>>  $items
     */
    protected function reference(string $modelClass, Site $site, array $items): void
    {
        foreach ($items as $order => $attributes) {
            $modelClass::query()->create(['site_id' => $site->id, 'sort_order' => $order, ...$attributes])
                ->forceFill(['is_published' => true])
                ->save();
        }
    }

    /**
     * Agenda, FAQ, leaders, an empty video gallery and an open admission period.
     *
     * @param  list<array{0: string, 1: string, 2: string}>  $leaders  position, name, expertise
     * @param  list<string>  $programs
     */
    protected function modules(Site $site, ?User $author, string $admissionName, array $leaders, array $programs): void
    {
        $events = [
            ['Kajian Kitab Bulanan', 'Kajian Rutin', 5, 'Masjid Jami\'', 'Terbuka untuk umum'],
            ['Ujian Lisan Akhir Semester', 'Imtihan', 12, 'Aula Utama', 'Wajib santri'],
            ['Peringatan Maulid Nabi', 'Peringatan Hari Besar', 20, 'Lapangan Utama', 'Terbuka untuk umum'],
        ];

        foreach ($events as [$title, $label, $days, $location, $audience]) {
            $startsAt = now()->addDays($days)->setTime(8, 0);

            $this->publish(Event::query()->make([
                'site_id' => $site->id,
                'author_id' => $author?->id,
                'title' => $title,
                'slug' => Str::slug($title),
                'label' => $label,
                'summary' => "Agenda {$title} bersama para asatidz dan santri.",
                'body' => $this->body($title),
                'location' => $location,
                'audience' => $audience,
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->copy()->addHours(4),
            ]), now()->subDay());
        }

        // Video items are left to the editors so the demo never embeds unknown videos.
        Gallery::query()->create([
            'site_id' => $site->id,
            'author_id' => $author?->id,
            'type' => GalleryType::Video,
            'title' => Str::of($site->name)->afterLast(' ')->append(' TV')->toString(),
            'slug' => 'tv',
            'description' => 'Kumpulan video kegiatan. Tambahkan tautan YouTube lalu terbitkan.',
        ]);

        foreach ($leaders as $order => [$position, $name, $expertise]) {
            Leader::query()->create([
                'site_id' => $site->id,
                'name' => $name,
                'position' => $position,
                'expertise' => $expertise,
                'bio' => '<p>'.e($name).' mengabdikan diri dalam pendidikan dan pembinaan santri selama lebih dari dua puluh tahun.</p>',
                'sort_order' => $order,
            ])->forceFill(['is_published' => true])->save();
        }

        $faqs = [
            ['Kapan pendaftaran dibuka?', "Pendaftaran {$admissionName} dibuka sesuai jadwal gelombang yang tertera di halaman penerimaan."],
            ['Apakah tersedia beasiswa?', 'Tersedia beasiswa prestasi dan beasiswa bagi santri dari keluarga kurang mampu.'],
            ['Bagaimana cara mendaftar online?', 'Isi formulir pendaftaran online, simpan nomor pendaftaran, lalu konfirmasi ke panitia melalui WhatsApp.'],
        ];

        foreach ($faqs as $order => [$question, $answer]) {
            Faq::query()->create([
                'site_id' => $site->id,
                'group' => 'Pendaftaran',
                'question' => $question,
                'answer' => '<p>'.e($answer).'</p>',
                'sort_order' => $order,
            ])->forceFill(['is_published' => true])->save();
        }

        $period = AdmissionPeriod::query()->create([
            'site_id' => $site->id,
            'title' => 'Gelombang II',
            'academic_year' => '2026/2027',
            'opens_on' => today()->subWeeks(2),
            'closes_on' => today()->addMonths(2),
            'summary' => "Penerimaan {$admissionName} gelombang kedua telah dibuka. Kuota terbatas.",
            'requirements' => '<ul><li>Fotokopi ijazah terakhir</li><li>Fotokopi kartu keluarga</li><li>Pas foto 3x4 (4 lembar)</li><li>Surat keterangan sehat</li></ul>',
            'fees' => '<p>Biaya pendaftaran Rp 250.000. Tersedia beasiswa penuh bagi santri berprestasi.</p>',
            'steps' => [
                ['title' => 'Pendaftaran Online', 'description' => 'Isi formulir dan simpan nomor pendaftaran.'],
                ['title' => 'Verifikasi Berkas', 'description' => 'Serahkan berkas persyaratan ke sekretariat.'],
                ['title' => 'Tes Masuk', 'description' => 'Tes baca Al-Qur\'an, kitab, dan wawancara.'],
                ['title' => 'Pengumuman', 'description' => 'Hasil seleksi diumumkan melalui website dan WhatsApp.'],
            ],
            'programs' => $programs,
            'accepts_online_registration' => true,
        ]);
        $period->forceFill(['is_published' => true])->save();

        foreach ([
            ['Kartu Keluarga', 'Scan atau foto jelas, seluruh halaman.', true, ['pdf', 'jpg', 'png']],
            ['Akta Kelahiran', null, true, ['pdf', 'jpg', 'png']],
            ['Pas Foto 3x4', 'Latar belakang merah, berpakaian rapi.', true, ['jpg', 'png']],
            ['Ijazah / Surat Keterangan Lulus', 'Boleh menyusul jika belum terbit.', false, ['pdf', 'jpg', 'png']],
        ] as $position => [$name, $description, $required, $types]) {
            $period->documentRequirements()->create([
                'name' => $name,
                'description' => $description,
                'is_required' => $required,
                'file_types' => $types,
                'sort_order' => $position,
            ]);
        }
    }

    /**
     * @param  array<string, bool>  $names  name => show on home
     * @return array<string, Category>
     */
    protected function categories(Site $site, array $names): array
    {
        $categories = [];
        $order = 0;

        foreach ($names as $name => $onHome) {
            $categories[$name] = Category::query()->create([
                'site_id' => $site->id,
                'name' => $name,
                'slug' => Str::slug($name),
                'show_on_home' => $onHome,
                'home_order' => $order++,
            ]);
        }

        return $categories;
    }

    /**
     * @param  array<string, Category>  $categories
     * @param  list<array{0: string, 1: string, 2?: bool}>  $posts  title, category, featured
     */
    protected function posts(Site $site, ?User $author, array $categories, array $posts): void
    {
        foreach ($posts as $index => [$title, $category]) {
            $this->publish(Post::query()->make([
                'site_id' => $site->id,
                'type' => $index % 3 === 1 ? PostType::Article : PostType::News,
                'category_id' => $categories[$category]->id,
                'author_id' => $author?->id,
                'title' => $title,
                'slug' => Str::slug($title),
                'excerpt' => "Ringkasan: {$title}. Kegiatan ini menjadi bagian dari pembinaan santri yang berkelanjutan.",
                'body' => $this->body($title),
                'is_featured' => $posts[$index][2] ?? false,
            ]), now()->subDays($index * 2 + 1));
        }
    }

    /**
     * @param  list<string>  $titles
     */
    protected function announcements(Site $site, ?User $author, array $titles): void
    {
        foreach ($titles as $index => $title) {
            $this->publish(Post::query()->make([
                'site_id' => $site->id,
                'type' => PostType::Announcement,
                'author_id' => $author?->id,
                'title' => $title,
                'slug' => Str::slug($title),
                'excerpt' => "Informasi resmi mengenai {$title}.",
                'body' => $this->body($title),
            ]), now()->subDays($index + 1));
        }
    }

    /**
     * @param  array<string, array{0: string, 1: ?string, 2: PageTemplate}>  $pages  slug => title, parent slug, template
     * @return array<string, Page>
     */
    protected function pages(Site $site, ?User $author, array $pages): array
    {
        $created = [];
        $order = 0;

        foreach ($pages as $slug => [$title, $parent, $template]) {
            $page = Page::query()->make([
                'site_id' => $site->id,
                'parent_id' => $parent ? $created[$parent]->id : null,
                'author_id' => $author?->id,
                'title' => $title,
                'slug' => $slug,
                'template' => $template,
                'sort_order' => $order++,
                'blocks' => [
                    ['id' => (string) Str::uuid(), 'type' => BlockType::RichText->value, 'data' => ['html' => $this->body($title)]],
                    ...($template === PageTemplate::Default && $parent === null ? [[
                        'id' => (string) Str::uuid(),
                        'type' => BlockType::CallToAction->value,
                        'data' => ['title' => 'Ingin tahu lebih banyak?', 'text' => 'Hubungi sekretariat kami untuk informasi lengkap.', 'button_label' => 'Hubungi Kami', 'button_url' => '/kontak'],
                    ]] : []),
                ],
            ]);

            $created[$slug] = $this->publish($page, now()->subMonth());
        }

        return $created;
    }

    /**
     * @param  list<array{0: string, 1: string|Page|Category|null, 2?: list<array{0: string, 1: string|Page|Category}>}>  $items
     */
    protected function menu(Site $site, MenuLocation $location, array $items): void
    {
        $menu = Menu::for($site, $location);

        foreach ($items as $order => $item) {
            $parent = $menu->items()->create(['label' => $item[0], 'sort_order' => $order, ...$this->target($item[1])]);

            foreach ($item[2] ?? [] as $childOrder => [$label, $target]) {
                $menu->items()->create(['label' => $label, 'parent_id' => $parent->id, 'sort_order' => $childOrder, ...$this->target($target)]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function target(string|Page|Category|null $target): array
    {
        return match (true) {
            $target instanceof Page => ['type' => MenuItemType::Page, 'linkable_type' => 'page', 'linkable_id' => $target->id],
            $target instanceof Category => ['type' => MenuItemType::Category, 'linkable_type' => 'category', 'linkable_id' => $target->id],
            default => ['type' => MenuItemType::Url, 'url' => $target ?? '#'],
        };
    }

    /**
     * @template TModel of Post|Page|Event|Publication
     *
     * @param  TModel  $content
     * @return TModel
     */
    protected function publish(Post|Page|Event|Publication $content, \DateTimeInterface $at): Post|Page|Event|Publication
    {
        $content->forceFill(['status' => ContentStatus::Published, 'published_at' => $at])->save();

        return $content;
    }

    protected function body(string $title): string
    {
        return '<p><strong>'.e($title).'</strong> merupakan salah satu agenda penting yang terus kami jalankan dengan penuh semangat dan keikhlasan.</p>'
            .'<p>Seluruh rangkaian kegiatan dipandu oleh para asatidz dan pengasuh, dengan tujuan membentuk pribadi santri yang berilmu, berakhlak, dan bermanfaat bagi umat.</p>'
            .'<blockquote>Barang siapa menempuh jalan untuk mencari ilmu, Allah mudahkan baginya jalan menuju surga. (HR. Muslim)</blockquote>'
            .'<ul><li>Pembinaan akhlak dan adab</li><li>Penguatan kajian kitab kuning</li><li>Pengembangan bakat dan prestasi</li></ul>';
    }
}
