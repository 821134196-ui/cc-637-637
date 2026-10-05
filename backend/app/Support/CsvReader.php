<?php

namespace App\Support;

use RuntimeException;

/**
 * Minimal CSV reader used to serve column-filtered slices of dataset files.
 * All authorization decisions happen before this is called; this class only
 * knows how to project a CSV down to an explicit allow-list of columns.
 */
class CsvReader
{
    /**
     * @return array{header: array<int, string>, rows: array<int, array<int, string>>}
     */
    public static function read(string $absolutePath, array $onlyColumns, ?int $limit = null): array
    {
        if (! is_file($absolutePath)) {
            throw new RuntimeException("Dataset file not found: {$absolutePath}");
        }

        $handle = fopen($absolutePath, 'r');
        if ($handle === false) {
            throw new RuntimeException("Cannot open dataset file: {$absolutePath}");
        }

        try {
            $header = fgetcsv($handle);
            if ($header === false) {
                return ['header' => [], 'rows' => []];
            }
            $header = array_map('trim', $header);

            // Only keep columns that both exist in the file AND are allow-listed.
            $keepIndexes = [];
            $keepNames = [];
            foreach ($header as $i => $name) {
                if (in_array($name, $onlyColumns, true)) {
                    $keepIndexes[] = $i;
                    $keepNames[] = $name;
                }
            }

            $rows = [];
            while (($row = fgetcsv($handle)) !== false) {
                $projected = [];
                foreach ($keepIndexes as $i) {
                    $projected[] = $row[$i] ?? '';
                }
                $rows[] = $projected;
                if ($limit !== null && count($rows) >= $limit) {
                    break;
                }
            }

            return ['header' => $keepNames, 'rows' => $rows];
        } finally {
            fclose($handle);
        }
    }

    /**
     * Render projected columns back into a CSV string.
     *
     * @param  array<int, string>  $header
     * @param  array<int, array<int, string>>  $rows
     */
    public static function toCsv(array $header, array $rows): string
    {
        $out = fopen('php://temp', 'r+');
        fputcsv($out, $header);
        foreach ($rows as $row) {
            fputcsv($out, $row);
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $csv;
    }
}
