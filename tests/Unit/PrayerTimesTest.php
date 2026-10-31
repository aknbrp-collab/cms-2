<?php

use App\Support\HijriDate;
use App\Support\PrayerTimes;
use Carbon\CarbonImmutable;

test('prayer times are in order and plausible for Jakarta', function () {
    $times = (new PrayerTimes(-6.2, 106.8167))->forDate(CarbonImmutable::parse('2026-10-31', 'Asia/Jakarta'));

    expect(array_keys($times))->toBe(['fajr', 'sunrise', 'dhuhr', 'asr', 'maghrib', 'isha'])
        ->and($times)->toEqual(collect($times)->sort()->all())
        ->and($times['dhuhr'])->toBeGreaterThanOrEqual('11:30')->toBeLessThanOrEqual('11:45')
        ->and($times['maghrib'])->toBeGreaterThanOrEqual('17:40')->toBeLessThanOrEqual('18:00')
        ->and($times['fajr'])->toBeGreaterThanOrEqual('03:55')->toBeLessThanOrEqual('04:20');
});

test('umm al-qura isha is ninety minutes after maghrib', function () {
    $times = (new PrayerTimes(21.42, 39.83, 'umm_al_qura'))->forDate(CarbonImmutable::parse('2026-03-01', 'Asia/Riyadh'));

    $maghrib = CarbonImmutable::createFromFormat('H:i', $times['maghrib']);
    $isha = CarbonImmutable::createFromFormat('H:i', $times['isha']);

    expect((int) $maghrib->diffInMinutes($isha))->toBe(90);
});

test('hijri dates use pesantren month names', function () {
    expect(HijriDate::format(CarbonImmutable::parse('2024-04-23', 'Asia/Jakarta')))->toBe('14 Syawwal 1445 H');
});
