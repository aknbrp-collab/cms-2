import Alpine from 'alpinejs';

/**
 * Countdown to the next prayer in the top bar.
 * `times` maps prayer names to "HH:MM" in the site's timezone offset (minutes).
 */
Alpine.data('prayerCountdown', (times, offsetMinutes) => ({
    label: '',

    init() {
        this.tick();
        setInterval(() => this.tick(), 30_000);
    },

    tick() {
        const now = new Date();
        const local = new Date(now.getTime() + (offsetMinutes + now.getTimezoneOffset()) * 60_000);
        const minutesNow = local.getHours() * 60 + local.getMinutes();

        const upcoming = Object.entries(times)
            .map(([name, time]) => {
                const [h, m] = time.split(':').map(Number);
                return { name, minutes: h * 60 + m };
            })
            .find((prayer) => prayer.minutes > minutesNow);

        if (!upcoming) {
            this.label = '';
            return;
        }

        const diff = upcoming.minutes - minutesNow;
        const hours = Math.floor(diff / 60);
        const rest = diff % 60;
        const wait = hours > 0 ? `${hours} jam ${rest} menit` : `${rest} menit`;

        this.label = `${upcoming.name} ${times[upcoming.name]} • ${wait} lagi`;
    },
}));

window.Alpine = Alpine;
Alpine.start();
