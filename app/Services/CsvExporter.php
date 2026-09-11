<?php

namespace App\Services;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Stream an Eloquent query to a UTF-8 CSV download.
 *
 * Rows are chunked straight onto the output buffer, so an export of a whole
 * year never materialises in memory. The byte order mark keeps Vietnamese
 * accents readable when the file is opened in Excel.
 */
class CsvExporter
{
    private const BOM = "\xEF\xBB\xBF";

    private const CHUNK = 500;

    /**
     * Characters a spreadsheet reads as the start of a formula.
     *
     * The tab and carriage return are included because Excel strips leading
     * whitespace before deciding, so " =1+1" is a formula too.
     */
    private const FORMULA_PREFIXES = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * @param  array<int, string>  $headings
     * @param  Closure(mixed): array<int, mixed>  $mapRow
     */
    public function stream(string $filename, array $headings, Builder $query, Closure $mapRow): StreamedResponse
    {
        return response()->streamDownload(function () use ($headings, $query, $mapRow): void {
            $handle = fopen('php://output', 'wb');

            fwrite($handle, self::BOM);
            fputcsv($handle, $headings);

            $query->chunkById(self::CHUNK, function ($rows) use ($handle, $mapRow): void {
                foreach ($rows as $row) {
                    fputcsv($handle, array_map(self::sanitise(...), $mapRow($row)));
                }

                flush();
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    /**
     * Neutralise a cell a spreadsheet would otherwise run as a formula.
     *
     * The value is prefixed with an apostrophe, which Excel and LibreOffice
     * treat as "this is text" and do not display. The text stays readable,
     * which matters: mangling or dropping the value would make the export
     * useless for the person who asked for it.
     *
     * Numbers are deliberately left alone. A money column legitimately holds
     * `-150000`, and quoting it as text would break every formula the
     * accountant writes on top of the file — the cure would be worse than the
     * disease, and a bare number cannot carry a payload anyway.
     */
    public static function sanitise(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        if (is_numeric($value)) {
            return $value;
        }

        return in_array($value[0], self::FORMULA_PREFIXES, true)
            ? "'".$value
            : $value;
    }
}
