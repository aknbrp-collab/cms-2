<?php

namespace App\Support;

use App\Models\Alumnus;
use DateTimeInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/**
 * Reads alumni rows (name, graduation year, city) from pasted text or a
 * CSV/Excel file and checks them before they are saved.
 */
class AlumniImport
{
    public const MAX_ROWS = 2000;

    /**
     * Rows of cells from pasted text or CSV: tab, semicolon or comma
     * separated, whichever the first line uses.
     *
     * @return list<list<string>>
     */
    public function fromText(string $text): array
    {
        $text = $this->toUtf8($text);
        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
        $first = collect($lines)->first(fn (string $line) => trim($line) !== '') ?? '';
        $delimiter = match (true) {
            str_contains($first, "\t") => "\t",
            str_contains($first, ';') => ';',
            default => ',',
        };

        $rows = [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $rows[] = array_map(fn ($cell) => trim((string) $cell), str_getcsv($line, $delimiter, '"', ''));
        }

        return $rows;
    }

    /**
     * Rows of cells from an uploaded .csv/.txt or .xlsx file (first sheet).
     *
     * @return list<list<string>>
     */
    public function fromFile(UploadedFile $file): array
    {
        if (strtolower($file->getClientOriginalExtension()) !== 'xlsx') {
            return $this->fromText((string) file_get_contents($file->getRealPath()));
        }

        $reader = new XlsxReader;
        $reader->open($file->getRealPath());
        $rows = [];

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $cells = array_map(fn ($value) => $this->cellToString($value), $row->toArray());
                    if (implode('', $cells) !== '') {
                        $rows[] = $cells;
                    }
                    if (count($rows) > self::MAX_ROWS + 1) {
                        break 2;
                    }
                }
                break;
            }
        } finally {
            $reader->close();
        }

        return $rows;
    }

    /**
     * Check each row: a header row is skipped, the year must be a 4-digit
     * year, and a name + year that already exists (or repeats) is a duplicate.
     *
     * @param  list<list<string>>  $rows
     * @return list<array{line: int, name: string, year: int|null, city: string, status: 'ok'|'error'|'duplicate', message: string}>
     */
    public function check(array $rows): array
    {
        if ($rows !== [] && $this->isHeader($rows[0])) {
            array_shift($rows);
            $offset = 2;
        } else {
            $offset = 1;
        }

        $parsed = [];
        foreach (array_slice($rows, 0, self::MAX_ROWS) as $index => $cells) {
            $parsed[] = ['line' => $index + $offset, ...$this->split($cells)];
        }

        $years = array_values(array_unique(array_filter(array_column($parsed, 'year'))));
        $existing = Alumnus::query()
            ->whereIn('graduation_year', $years)
            ->get(['name', 'graduation_year'])
            ->mapWithKeys(fn (Alumnus $alumnus) => [$this->key($alumnus->name, $alumnus->graduation_year) => true])
            ->all();

        $seen = [];
        $checked = [];
        foreach ($parsed as $row) {
            [$status, $message] = match (true) {
                $row['name'] === '' => ['error', __('Name is missing.')],
                mb_strlen($row['name']) > 255 => ['error', __('Name is too long.')],
                $row['year'] === null => ['error', __('Graduation year is missing or not a year (e.g. 2018).')],
                mb_strlen($row['city']) > 120 => ['error', __('City is too long.')],
                isset($existing[$this->key($row['name'], $row['year'])]) => ['duplicate', __('Already in the alumni list.')],
                isset($seen[$this->key($row['name'], $row['year'])]) => ['duplicate', __('Repeated in this import.')],
                default => ['ok', ''],
            };

            if ($status === 'ok') {
                $seen[$this->key($row['name'], (int) $row['year'])] = true;
            }

            $checked[] = [...$row, 'status' => $status, 'message' => $message];
        }

        return $checked;
    }

    /**
     * Name, year and city from a row. The year is the first cell that is a
     * 4-digit year; cells before it form the name (so "Ahmad, S.Pd." stays
     * whole) and cells after it the city.
     *
     * @param  list<string>  $cells
     * @return array{name: string, year: int|null, city: string}
     */
    protected function split(array $cells): array
    {
        $yearIndex = null;

        foreach ($cells as $index => $cell) {
            if ($index > 0 && preg_match('/^(19|20|21)\d{2}$/', $cell)) {
                $yearIndex = $index;
                break;
            }
        }

        if ($yearIndex === null) {
            return ['name' => Str::squish($cells[0] ?? ''), 'year' => null, 'city' => Str::squish(implode(', ', array_filter(array_slice($cells, 2))))];
        }

        $year = (int) $cells[$yearIndex];

        return [
            'name' => Str::squish(implode(', ', array_filter(array_slice($cells, 0, $yearIndex), fn ($cell) => $cell !== ''))),
            'year' => $year >= 1900 && $year <= 2100 ? $year : null,
            'city' => Str::squish(implode(', ', array_filter(array_slice($cells, $yearIndex + 1), fn ($cell) => $cell !== ''))),
        ];
    }

    /**
     * @param  list<string>  $cells
     */
    protected function isHeader(array $cells): bool
    {
        return in_array(Str::lower(trim($cells[0] ?? '')), ['nama', 'name', 'nama lengkap', 'nama alumni'], true);
    }

    protected function key(string $name, int $year): string
    {
        return Str::lower(Str::squish($name)).'|'.$year;
    }

    protected function cellToString(mixed $value): string
    {
        return trim(match (true) {
            $value instanceof DateTimeInterface => $value->format('Y'),
            is_float($value) && floor($value) === $value => (string) (int) $value,
            is_scalar($value) => (string) $value,
            default => '',
        });
    }

    protected function toUtf8(string $text): string
    {
        $text = (string) preg_replace('/^\xEF\xBB\xBF/', '', $text);

        return mb_check_encoding($text, 'UTF-8') ? $text : (string) mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
    }
}
