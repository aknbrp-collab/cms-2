<?php

namespace App\Services\Restore;

use Illuminate\Support\Facades\File;

/**
 * Progress of a restore, kept in a file: the database, cache and sessions
 * may be replaced while it runs.
 */
class RestoreStatus
{
    /**
     * Steps in order, as shown on the Data page.
     */
    public const STEPS = ['checking', 'backing_up', 'preparing', 'restoring_database', 'restoring_files', 'done'];

    public function path(): string
    {
        return storage_path('app/restore-status.json');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(): ?array
    {
        if (! File::exists($this->path())) {
            return null;
        }

        $status = json_decode((string) File::get($this->path()), true);

        return is_array($status) ? $status : null;
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public function update(array $changes): void
    {
        File::put($this->path(), (string) json_encode([...($this->get() ?? []), ...$changes, 'updated_at' => now()->toIso8601String()]));
    }

    /**
     * Whether a restore is running (one silent for three hours counts as stuck).
     */
    public function running(): bool
    {
        $status = $this->get();

        return $status !== null
            && ! in_array($status['state'] ?? null, ['done', 'failed'], true)
            && now()->subHours(3)->lt((string) ($status['updated_at'] ?? 'now'));
    }

    public function clear(): void
    {
        File::delete($this->path());
    }
}
