<?php

namespace App\Services;

use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvExportService
{
    /**
     * Characters that make a spreadsheet treat a cell as a formula.
     */
    private const FORMULA_PREFIXES = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * @param  list<string>  $headers
     * @param  iterable<int, list<string|int|float|null>>  $rows
     */
    public function download(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, array_map([$this, 'escape'], $headers));
            foreach ($rows as $row) {
                fputcsv($out, array_map([$this, 'escape'], $row));
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Neutralise CSV formula injection.
     *
     * Exported cells include free text people typed (expense descriptions, ledger
     * notes), so a cell starting with `=`, `+`, `-` or `@` would run as a formula
     * when the file is opened in Excel or Sheets. Numbers are left alone so
     * negative amounts still import as numbers.
     */
    private function escape(string|int|float|null $value): string
    {
        if ($value === null) {
            return '';
        }

        $string = (string) $value;

        if ($string === '' || is_numeric($string)) {
            return $string;
        }

        foreach (self::FORMULA_PREFIXES as $prefix) {
            if (str_starts_with($string, $prefix)) {
                return "'".$string;
            }
        }

        return $string;
    }
}
