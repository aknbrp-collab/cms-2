<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Enums\SocialProvider;
use App\Models\Site;
use App\Models\User;
use App\Social\SocialManager;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Symfony\Component\Process\ExecutableFinder;
use Throwable;

/**
 * Production readiness check, run on every deploy (see docs/deployment.md).
 * Failures exit non-zero; warnings are printed but do not fail the deploy.
 */
#[Signature('cms:check')]
#[Description('Check that the configuration is safe and complete for production')]
class CheckReadiness extends Command
{
    private const OK = 'ok';

    private const WARN = 'warn';

    private const FAIL = 'fail';

    /**
     * @var list<array{string, string, string}>
     */
    private array $results = [];

    public function handle(SocialManager $social): int
    {
        $this->checkApplication();
        $this->checkDatabase();
        $this->checkServices();
        $this->checkIntegrations($social);
        $this->checkBackups();

        $this->table(['', 'Check', 'Detail'], array_map(fn (array $row) => [
            match ($row[0]) {
                self::OK => '<fg=green>OK</>',
                self::WARN => '<fg=yellow>WARN</>',
                default => '<fg=red>FAIL</>',
            },
            $row[1],
            $row[2],
        ], $this->results));

        $failures = count(array_filter($this->results, fn (array $row) => $row[0] === self::FAIL));

        if ($failures > 0) {
            $this->error("{$failures} check(s) failed.");

            return self::FAILURE;
        }

        $this->info('Ready.');

        return self::SUCCESS;
    }

    private function checkApplication(): void
    {
        $production = app()->isProduction();

        $this->result($production ? self::OK : self::WARN, 'APP_ENV', $production ? 'production' : 'is "'.app()->environment().'", expected production');
        $this->result(config('app.debug') ? self::FAIL : self::OK, 'APP_DEBUG', config('app.debug') ? 'must be false: debug pages leak secrets' : 'off');
        $this->result(filled(config('app.key')) ? self::OK : self::FAIL, 'APP_KEY', filled(config('app.key')) ? 'set (keep a copy: social tokens are encrypted with it)' : 'missing');
        $this->result(Str::startsWith((string) config('app.url'), 'https://') ? self::OK : self::FAIL, 'APP_URL', (string) config('app.url'));
        $this->result(filled(config('cms.admin_domain')) ? self::OK : self::FAIL, 'ADMIN_DOMAIN', (string) (config('cms.admin_domain') ?: 'missing'));
        $this->result(config('session.secure') ? self::OK : self::FAIL, 'SESSION_SECURE_COOKIE', config('session.secure') ? 'on' : 'must be true behind HTTPS');
        $this->result(app()->configurationIsCached() ? self::OK : self::WARN, 'Config cache', app()->configurationIsCached() ? 'cached' : 'run php artisan optimize');
        $this->result(file_exists(public_path('storage')) ? self::OK : self::WARN, 'Storage link', file_exists(public_path('storage')) ? 'public/storage exists' : 'run php artisan storage:link');
    }

    private function checkDatabase(): void
    {
        try {
            DB::connection()->getPdo();
        } catch (Throwable $e) {
            $this->result(self::FAIL, 'Database', $e->getMessage());

            return;
        }

        $driver = DB::connection()->getDriverName();
        $this->result($driver === 'pgsql' ? self::OK : self::FAIL, 'Database driver', $driver === 'pgsql' ? 'PostgreSQL' : "{$driver}: PostgreSQL is required for the immutable audit log");

        if ($driver === 'pgsql') {
            $triggers = DB::table('pg_trigger')->whereIn('tgname', ['audit_logs_no_update_delete', 'audit_logs_no_truncate'])->count();
            $this->result($triggers === 2 ? self::OK : self::FAIL, 'Audit log protection', $triggers === 2 ? 'update/delete/truncate blocked' : 'triggers missing: run php artisan migrate');
        }

        $sites = Site::query()->pluck('domain', 'key');
        $local = $sites->filter(fn (?string $domain) => blank($domain) || Str::endsWith($domain, ['.test', '.localhost', 'localhost']));
        $this->result($sites->isNotEmpty() && $local->isEmpty() ? self::OK : self::FAIL, 'Site domains', $sites->isEmpty() ? 'no sites: run php artisan db:seed' : $sites->map(fn ($domain, $key) => "{$key}={$domain}")->implode(', '));

        $superadmins = User::role(Role::Superadmin->value)->count();
        $this->result($superadmins > 0 ? self::OK : self::FAIL, 'Superadmin', $superadmins > 0 ? "{$superadmins} account(s)" : 'none: run php artisan cms:create-superadmin');
    }

    private function checkServices(): void
    {
        $queue = (string) config('queue.default');
        $this->result($queue === 'redis' ? self::OK : self::WARN, 'Queue', $queue === 'redis' ? 'redis (Horizon)' : "{$queue}: use redis with Horizon in production");

        if (in_array('redis', [$queue, config('cache.default'), config('session.driver')], true)) {
            try {
                Redis::connection()->ping();
                $this->result(self::OK, 'Redis', 'reachable');
            } catch (Throwable $e) {
                $this->result(self::FAIL, 'Redis', $e->getMessage());
            }
        }

        $mailer = (string) config('mail.default');
        $this->result(in_array($mailer, ['log', 'array'], true) ? self::FAIL : self::OK, 'Mail', in_array($mailer, ['log', 'array'], true) ? "{$mailer}: notifications and password resets would not be delivered" : $mailer);
        $this->result(filled(config('cms.alert_email')) ? self::OK : self::WARN, 'CMS_ALERT_EMAIL', (string) (config('cms.alert_email') ?: 'not set: nobody is told when backups fail'));
    }

    private function checkIntegrations(SocialManager $social): void
    {
        $mediaDisk = (string) config('media-library.disk_name');

        if ($social->isLive()) {
            foreach ([SocialProvider::Facebook, SocialProvider::TikTok] as $provider) {
                $name = $provider->oauthApp() === 'tiktok' ? 'TikTok app keys' : 'Meta app keys';
                $this->result($social->isConfigured($provider) ? self::OK : self::WARN, $name, $social->isConfigured($provider) ? 'set' : 'missing: this platform cannot be connected');
            }

            $this->result($mediaDisk === 's3' ? self::OK : self::WARN, 'MEDIA_DISK', $mediaDisk === 's3' ? 's3' : "{$mediaDisk}: Instagram and TikTok must fetch media from a public URL (S3/CDN)");
        } else {
            $this->result(self::WARN, 'SOCIAL_DRIVER', 'fake: publishing is simulated');
        }

        if (config('siakad.driver') === 'http') {
            $complete = filled(config('siakad.base_url')) && filled(config('siakad.api_key'));
            $this->result($complete ? self::OK : self::FAIL, 'SIAKAD client', $complete ? (string) config('siakad.base_url') : 'SIAKAD_BASE_URL and SIAKAD_API_KEY are required');
        } else {
            $this->result(self::WARN, 'SIAKAD_DRIVER', 'fake: forwarded questions are not sent');
        }

        $secret = (string) config('siakad.webhook_secret');
        $this->result(strlen($secret) >= 32 ? self::OK : self::WARN, 'SIAKAD_WEBHOOK_SECRET', strlen($secret) >= 32 ? 'set' : 'missing or shorter than 32 characters: SIAKAD webhooks are refused');
    }

    private function checkBackups(): void
    {
        /** @var list<string> $disks */
        $disks = config('backup.backup.destination.disks');
        $offsite = array_filter($disks, fn (string $disk) => config("filesystems.disks.{$disk}.driver") !== 'local');

        $this->result($offsite !== [] ? self::OK : self::WARN, 'Backup disks', implode(', ', $disks).($offsite !== [] ? '' : ': add an off-site disk (BACKUP_DISKS=local,backups)'));
        $this->result(filled(config('backup.backup.password')) ? self::OK : self::WARN, 'Backup encryption', filled(config('backup.backup.password')) ? 'archives are encrypted' : 'set BACKUP_ARCHIVE_PASSWORD');

        $binary = rtrim((string) config('database.connections.pgsql.dump.dump_binary_path'), '/\\');
        $found = $binary !== '' ? is_file($binary.DIRECTORY_SEPARATOR.'pg_dump') || is_file($binary.DIRECTORY_SEPARATOR.'pg_dump.exe') : (new ExecutableFinder)->find('pg_dump') !== null;
        $this->result($found ? self::OK : self::FAIL, 'pg_dump', $found ? 'found' : 'not found: install postgresql-client or set DB_DUMP_BINARY_PATH');
    }

    private function result(string $status, string $check, string $detail): void
    {
        $this->results[] = [$status, $check, $detail];
    }
}
