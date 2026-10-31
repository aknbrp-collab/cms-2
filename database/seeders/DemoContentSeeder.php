<?php

namespace Database\Seeders;

use App\Enums\BlockType;
use App\Enums\ContentStatus;
use App\Enums\GalleryType;
use App\Enums\MenuItemType;
use App\Enums\MenuLocation;
use App\Enums\PageTemplate;
use App\Enums\PostType;
use App\Enums\SiteKey;
use App\Models\AdmissionPeriod;
use App\Models\Category;
use App\Models\Event;
use App\Models\Faq;
use App\Models\Gallery;
use App\Models\Leader;
use App\Models\Menu;
use App\Models\Page;
use App\Models\Post;
use App\Models\Site;
use App\Models\User;
use App\Support\CurrentSite;
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

        $this->modules($site, $author, 'PSB', [
            ['Pengasuh Pondok Pesantren', 'KH. Ahmad Mudzakir', 'Sanad Tahfidz & Kitab Kuning'],
            ['Kepala Madrasah', 'Ust. H. Muhammad Ridwan, M.Pd.I', 'Manajemen Pendidikan Islam'],
        ], ['Reguler', 'Tahfidz Al-Qur\'an', 'Bahasa Arab & Inggris']);

        $this->menu($site, MenuLocation::Header, [
            ['Profil', null, [['Sejarah', $pages['sejarah']], ['Visi & Misi', $pages['visi-misi']], ['Pimpinan', '/pimpinan']]],
            ['Berita', null, [['Berita', '/berita'], ['Pengumuman', '/pengumuman'], ['Agenda', '/agenda']]],
            ['Pendaftaran', null, [['Info PSB', '/penerimaan'], ['Pendaftaran Online', '/penerimaan/daftar'], ['FAQ', '/faq']]],
            ['Galeri', null, [['Foto', '/galeri?jenis=photo'], ['Video', '/galeri?jenis=video']]],
            ['Kontak', $pages['kontak']],
        ]);
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

        $this->modules($site, $author, 'PMB', [
            ['Rais Masyayikh', 'Syaikh Dr. KH. Luqmanul Hakim, MA.', 'Sanad Ushul Fiqh Al-Azhar Kairo'],
            ['Mudir Ma\'had Aly', 'KH. M. Syukron Ma\'mun, Lc., M.Hum.', 'Tahqiq Turats Fiqh Syafi\'i'],
            ['Wakil Mudir Bidang Akademik', 'Dr. H. Mahbub Ridwan, M.Ag.', 'Hadits wa \'Ulumuhu'],
        ], ['Marhalah Ula (S1) Fiqh wa Ushuluh', 'Marhalah Ula (S1) Hadits wa \'Ulumuhu', 'Marhalah Tsaniyah (S2) Fiqh Mu\'amalah']);

        $this->menu($site, MenuLocation::Header, [
            ['Profil', null, [['Tentang Kami', $pages['tentang-kami']], ['Visi & Misi', $pages['visi-dan-misi']], ['Sejarah & Sanad', $pages['sejarah-dan-sanad']], ['Pimpinan', '/pimpinan']]],
            ['Publikasi & Riset', null, [['Bahtsul Masail', $categories['Bahtsul Masail']], ['Kajian Ilmiah', $categories['Kajian Ilmiah']]]],
            ['Berita & Agenda', null, [['Berita Akademik', '/berita'], ['Agenda & Halaqah', '/agenda'], ['Galeri', '/galeri']]],
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

        AdmissionPeriod::query()->create([
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
        ])->forceFill(['is_published' => true])->save();
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
     * @template TModel of Post|Page|Event
     *
     * @param  TModel  $content
     * @return TModel
     */
    protected function publish(Post|Page|Event $content, \DateTimeInterface $at): Post|Page|Event
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
