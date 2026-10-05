<?php

namespace App\Http\Controllers;

use App\Models\Dataset;
use App\Models\DatasetVersion;
use App\Support\CsvReader;
use Illuminate\Support\Facades\Storage;

/**
 * Public catalog. These endpoints are reachable WITHOUT authentication and
 * must therefore never expose restricted field names or restricted data.
 */
class CatalogController extends Controller
{
    public function index()
    {
        $datasets = Dataset::with(['latestVersion.fields'])->get()->map(function (Dataset $dataset) {
            $version = $dataset->latestVersion;

            return [
                'id' => $dataset->id,
                'name' => $dataset->name,
                'code' => $dataset->code,
                'description' => $dataset->description,
                'latest_version' => $version ? [
                    'id' => $version->id,
                    'version' => $version->version,
                    'created_at' => $version->created_at,
                ] : null,
                'public_fields' => $version
                    ? $version->fields->where('is_restricted', false)->values()->map(fn ($f) => $this->fieldPayload($f))
                    : [],
                'restricted_field_count' => $version
                    ? $version->fields->where('is_restricted', true)->count()
                    : 0,
            ];
        });

        return response()->json($datasets);
    }

    public function show(Dataset $dataset)
    {
        $dataset->load(['versions.fields']);

        return response()->json([
            'id' => $dataset->id,
            'name' => $dataset->name,
            'code' => $dataset->code,
            'description' => $dataset->description,
            'versions' => $dataset->versions->sortByDesc('id')->values()->map(fn (DatasetVersion $v) => [
                'id' => $v->id,
                'version' => $v->version,
                'changelog' => $v->changelog,
                'created_at' => $v->created_at,
                'public_fields' => $v->fields->where('is_restricted', false)->values()->map(fn ($f) => $this->fieldPayload($f)),
                'restricted_field_count' => $v->fields->where('is_restricted', true)->count(),
            ]),
        ]);
    }

    /**
     * Preview rows of the latest version, restricted columns stripped
     * server-side. Never returns restricted field names or values.
     */
    public function preview(Dataset $dataset)
    {
        $version = $dataset->latestVersion()->with('fields')->first();
        if (! $version) {
            return response()->json(['columns' => [], 'rows' => []]);
        }

        $publicColumns = $version->fields->where('is_restricted', false)->pluck('name')->all();
        $data = CsvReader::read(Storage::path($version->file_path), $publicColumns, limit: 5);

        return response()->json([
            'version' => $version->version,
            'columns' => $data['header'],
            'rows' => $data['rows'],
        ]);
    }

    private function fieldPayload($field): array
    {
        return [
            'name' => $field->name,
            'label' => $field->label,
            'data_type' => $field->data_type,
            'description' => $field->description,
        ];
    }
}
