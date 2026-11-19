<?php

namespace App\Services\Restore;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * Loads a SQL file into the PostgreSQL database with `psql`, in one
 * transaction: if any statement fails nothing is changed.
 */
class PostgresLoader
{
    /**
     * Whether psql can be run, with its version or the error.
     *
     * @return array{ok: bool, message: string}
     */
    public function check(): array
    {
        if (config('database.default') !== 'pgsql') {
            return ['ok' => false, 'message' => 'The database is not PostgreSQL (DB_CONNECTION='.config('database.default').').'];
        }

        $result = Process::timeout(15)->run([$this->binary(), '--version']);

        return $result->successful()
            ? ['ok' => true, 'message' => trim($result->output())]
            : ['ok' => false, 'message' => 'psql not found. Install postgresql-client or set DB_DUMP_BINARY_PATH.'];
    }

    /**
     * Run the file with ON_ERROR_STOP in a single transaction.
     *
     * @return array{ok: bool, message: string}
     */
    public function load(string $sqlFile): array
    {
        $connection = config('database.connections.pgsql');

        $result = Process::timeout(3600)
            ->env(['PGPASSWORD' => (string) $connection['password']])
            ->run([
                $this->binary(),
                '--host='.$connection['host'],
                '--port='.$connection['port'],
                '--username='.$connection['username'],
                '--dbname='.$connection['database'],
                '--no-psqlrc',
                '--quiet',
                '--single-transaction',
                '--set=ON_ERROR_STOP=1',
                '--file='.$sqlFile,
            ]);

        return $result->successful()
            ? ['ok' => true, 'message' => '']
            : ['ok' => false, 'message' => Str::limit(trim($result->errorOutput() ?: $result->output()), 500)];
    }

    protected function binary(): string
    {
        $directory = rtrim((string) config('database.connections.pgsql.dump.dump_binary_path'), '/\\');

        return $directory === '' ? 'psql' : $directory.DIRECTORY_SEPARATOR.'psql';
    }
}
