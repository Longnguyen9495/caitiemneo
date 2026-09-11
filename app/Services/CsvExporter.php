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
                    fputcsv($handle, $mapRow($row));
                }

                flush();
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }
}
