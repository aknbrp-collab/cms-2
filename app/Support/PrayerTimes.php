<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Calculate the five daily prayer times for a location.
 *
 * Based on the widely used PrayTimes.org algorithm (sun position by
 * Astronomical Almanac approximation). Asr uses the Shafi'i shadow factor.
 */
class PrayerTimes
{
    /**
     * Fajr and Isha twilight angles per method. An Isha value with "min"
     * is a fixed interval after Maghrib.
     *
     * @var array<string, array{fajr: float, isha: float|string, ihtiyat: int}>
     */
    public const METHODS = [
        'kemenag' => ['fajr' => 20.0, 'isha' => 18.0, 'ihtiyat' => 2],
        'mwl' => ['fajr' => 18.0, 'isha' => 17.0, 'ihtiyat' => 0],
        'umm_al_qura' => ['fajr' => 18.5, 'isha' => '90 min', 'ihtiyat' => 0],
        'egyptian' => ['fajr' => 19.5, 'isha' => 17.5, 'ihtiyat' => 0],
    ];

    /**
     * Display names, in order.
     */
    public const NAMES = [
        'fajr' => 'Subuh',
        'sunrise' => 'Terbit',
        'dhuhr' => 'Dzuhur',
        'asr' => 'Ashar',
        'maghrib' => 'Maghrib',
        'isha' => 'Isya',
    ];

    protected float $julianDate;

    public function __construct(
        protected float $latitude,
        protected float $longitude,
        protected string $method = 'kemenag',
    ) {}

    /**
     * Prayer times as "HH:MM" strings keyed by prayer, in the date's timezone.
     *
     * @return array{fajr: string, sunrise: string, dhuhr: string, asr: string, maghrib: string, isha: string}
     */
    public function forDate(CarbonInterface $date): array
    {
        $date = CarbonImmutable::instance($date);
        $timezone = $date->getOffset() / 3600;
        $method = self::METHODS[$this->method] ?? self::METHODS['kemenag'];

        $this->julianDate = $this->julian($date->year, $date->month, $date->day) - $this->longitude / (15 * 24);

        // One refinement pass from rough day-fraction guesses is accurate to the minute.
        $times = [
            'fajr' => $this->sunAngleTime($method['fajr'], 5 / 24, true),
            'sunrise' => $this->sunAngleTime(0.833, 6 / 24, true),
            'dhuhr' => $this->midDay(12 / 24),
            'asr' => $this->asrTime(1, 13 / 24),
            'maghrib' => $this->sunAngleTime(0.833, 18 / 24),
            'isha' => is_float($method['isha']) ? $this->sunAngleTime($method['isha'], 18 / 24) : 0.0,
        ];

        foreach ($times as $name => $time) {
            $times[$name] = $time + $timezone - $this->longitude / 15;
        }

        if (! is_float($method['isha'])) {
            $times['isha'] = $times['maghrib'] + ((int) $method['isha']) / 60;
        }

        $ihtiyat = $method['ihtiyat'] / 60;

        return [
            'fajr' => $this->format($times['fajr'] + $ihtiyat),
            'sunrise' => $this->format($times['sunrise'] - $ihtiyat),
            'dhuhr' => $this->format($times['dhuhr'] + $ihtiyat),
            'asr' => $this->format($times['asr'] + $ihtiyat),
            'maghrib' => $this->format($times['maghrib'] + $ihtiyat),
            'isha' => $this->format($times['isha'] + $ihtiyat),
        ];
    }

    protected function julian(int $year, int $month, int $day): float
    {
        if ($month <= 2) {
            $year--;
            $month += 12;
        }

        $a = floor($year / 100);
        $b = 2 - $a + floor($a / 4);

        return floor(365.25 * ($year + 4716)) + floor(30.6001 * ($month + 1)) + $day + $b - 1524.5;
    }

    /**
     * @return array{declination: float, equation: float}
     */
    protected function sunPosition(float $julianDate): array
    {
        $d = $julianDate - 2451545.0;
        $g = $this->fixAngle(357.529 + 0.98560028 * $d);
        $q = $this->fixAngle(280.459 + 0.98564736 * $d);
        $l = $this->fixAngle($q + 1.915 * $this->sin($g) + 0.020 * $this->sin(2 * $g));
        $e = 23.439 - 0.00000036 * $d;

        $rightAscension = rad2deg(atan2($this->cos($e) * $this->sin($l), $this->cos($l))) / 15;

        return [
            'declination' => rad2deg(asin($this->sin($e) * $this->sin($l))),
            'equation' => $q / 15 - $this->fixHour($rightAscension),
        ];
    }

    protected function midDay(float $time): float
    {
        return $this->fixHour(12 - $this->sunPosition($this->julianDate + $time)['equation']);
    }

    protected function sunAngleTime(float $angle, float $time, bool $beforeNoon = false): float
    {
        $declination = $this->sunPosition($this->julianDate + $time)['declination'];
        $noon = $this->midDay($time);
        $cosine = (-$this->sin($angle) - $this->sin($declination) * $this->sin($this->latitude))
            / ($this->cos($declination) * $this->cos($this->latitude));
        $offset = rad2deg(acos(max(-1.0, min(1.0, $cosine)))) / 15;

        return $noon + ($beforeNoon ? -$offset : $offset);
    }

    protected function asrTime(float $shadowFactor, float $time): float
    {
        $declination = $this->sunPosition($this->julianDate + $time)['declination'];
        $angle = -rad2deg(atan(1 / ($shadowFactor + tan(deg2rad(abs($this->latitude - $declination))))));

        return $this->sunAngleTime($angle, $time);
    }

    protected function format(float $hours): string
    {
        $minutes = (int) round($this->fixHour($hours) * 60) % (24 * 60);

        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    protected function sin(float $degrees): float
    {
        return sin(deg2rad($degrees));
    }

    protected function cos(float $degrees): float
    {
        return cos(deg2rad($degrees));
    }

    protected function fixAngle(float $angle): float
    {
        return $angle - 360 * floor($angle / 360);
    }

    protected function fixHour(float $hour): float
    {
        return $hour - 24 * floor($hour / 24);
    }
}
