<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dataset;
use App\Models\DatasetField;
use App\Models\DatasetVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class DatasetController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:64', 'alpha_dash', Rule::unique('datasets', 'code')],
            'description' => ['nullable', 'string'],
        ]);

        $dataset = Dataset::create($data);

        return response()->json($dataset, 201);
    }

    /**
     * Publish a new version of a dataset by uploading a CSV file.
     *
     * IMPORTANT: this only ADDS a version. Existing grants stay pinned to
     * the version they were approved for — a dataset update never widens
     * any existing authorization.
     */
    public function storeVersion(Request $request, Dataset $dataset)
    {
        $data = $request->validate([
            'version' => [
                'required', 'string', 'max:64',
                Rule::unique('dataset_versions', 'version')->where('dataset_id', $dataset->id),
            ],
            'changelog' => ['nullable', 'string'],
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:20480'],
            'restricted_fields' => ['nullable', 'array'],
            'restricted_fields.*' => ['string'],
        ]);

        $file = $data['file'];
        $header = $this->readHeader($file->getRealPath());
        if ($header === []) {
            return response()->json(['message' => 'CSV 文件缺少表头'], 422);
        }

        $restricted = $data['restricted_fields'] ?? [];
        $unknownRestricted = array_diff($restricted, $header);
        if ($unknownRestricted !== []) {
            return response()->json([
                'message' => '受限字段不在 CSV 表头中: '.implode(', ', $unknownRestricted),
            ], 422);
        }

        $version = DB::transaction(function () use ($dataset, $data, $file, $header, $restricted) {
            $path = $file->storeAs(
                "datasets/{$dataset->id}",
                'v'.preg_replace('/[^A-Za-z0-9._-]/', '_', $data['version']).'.csv'
            );

            $version = DatasetVersion::create([
                'dataset_id' => $dataset->id,
                'version' => $data['version'],
                'file_path' => $path,
                'changelog' => $data['changelog'] ?? null,
            ]);

            foreach ($header as $i => $name) {
                DatasetField::create([
                    'dataset_version_id' => $version->id,
                    'name' => $name,
                    'label' => $name,
                    'data_type' => 'string',
                    'is_restricted' => in_array($name, $restricted, true),
                    'sort_order' => $i,
                ]);
            }

            return $version;
        });

        return response()->json([
            'id' => $version->id,
            'version' => $version->version,
            'fields' => $version->fields()->get(['name', 'is_restricted']),
        ], 201);
    }

    /**
     * @return array<int, string>
     */
    private function readHeader(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return [];
        }
        $header = fgetcsv($handle);
        fclose($handle);

        return $header === false ? [] : array_map('trim', $header);
    }
}
