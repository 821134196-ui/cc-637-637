<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class DatasetVersion extends Model
{
    protected $fillable = ['dataset_id', 'version', 'file_path', 'changelog'];

    public function dataset(): BelongsTo
    {
        return $this->belongsTo(Dataset::class);
    }

    /**
     * Read the CSV data file for this version.
     *
     * @return array{headers: array<int, string>, rows: array<int, array<string, string>>}
     */
    public function readCsv(): array
    {
        $path = Storage::disk('local')->path($this->file_path);
        $handle = fopen($path, 'r');
        abort_if($handle === false, 500, 'Data file unavailable');

        $headers = fgetcsv($handle);
        $rows = [];
        while (($line = fgetcsv($handle)) !== false) {
            $row = [];
            foreach ($headers as $i => $header) {
                $row[$header] = $line[$i] ?? '';
            }
            $rows[] = $row;
        }
        fclose($handle);

        return ['headers' => $headers, 'rows' => $rows];
    }
}
