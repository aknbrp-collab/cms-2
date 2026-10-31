<?php

namespace Database\Factories;

use App\Models\AdmissionPeriod;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdmissionPeriod>
 */
class AdmissionPeriodFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'title' => 'Gelombang I',
            'academic_year' => '2026/2027',
            'opens_on' => today()->subWeek(),
            'closes_on' => today()->addMonth(),
            'summary' => 'Pendaftaran santri baru telah dibuka.',
            'steps' => [
                ['title' => 'Daftar online', 'description' => 'Isi formulir pendaftaran.'],
                ['title' => 'Tes masuk', 'description' => 'Tes membaca Al-Qur\'an dan wawancara.'],
            ],
            'programs' => ['Reguler', 'Tahfidz'],
            'accepts_online_registration' => true,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => ['is_published' => true]);
    }

    public function closed(): static
    {
        return $this->state(fn () => ['opens_on' => today()->subMonths(2), 'closes_on' => today()->subMonth()]);
    }
}
