<?php

namespace App\Http\Controllers;

use App\Models\Dataset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DatasetAdminController extends Controller
{
    /**
     * Approver list: all datasets with versions and grants, for management.
     */
    public function index()
    {
        $datasets = Dataset::with(['fields', 'versions', 'grants.user:id,name,email'])
            ->orderBy('id')
            ->get();

        return response()->json(['datasets' => $datasets]);
    }

    /**
     * Create a dataset with its field dictionary and first version (CSV).
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'department' => ['nullable', 'string', 'max:255'],
            'fields' => ['required', 'array', 'min:1'],
            'fields.*.name' => ['required', 'string', 'max:255'],
            'fields.*.label' => ['nullable', 'string', 'max:255'],
            'fields.*.description' => ['nullable', 'string'],
            'fields.*.is_restricted' => ['boolean'],
            'csv' => ['required', 'string'], // raw CSV text, header row first
        ]);

        $dataset = DB::transaction(function () use ($request, $data) {
            $dataset = Dataset::create([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'department' => $data['department'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            foreach (array_values($data['fields']) as $i => $field) {
                $dataset->fields()->create([
                    'name' => $field['name'],
                    'label' => $field['label'] ?? $field['name'],
                    'description' => $field['description'] ?? null,
                    'is_restricted' => $field['is_restricted'] ?? false,
                    'position' => $i,
                ]);
            }

            $this->storeVersion($dataset, 1, $data['csv'], '初始版本');

            return $dataset;
        });

        return response()->json(['dataset' => $dataset->load(['fields', 'versions'])], 201);
    }

    /**
     * Publish a new version of an existing dataset.
     *
     * Existing grants stay pinned to their approved version and field
     * snapshot — nothing here widens them.
     */
    public function addVersion(Request $request, Dataset $dataset)
    {
        $data = $request->validate([
            'csv' => ['required', 'string'],
            'changelog' => ['nullable', 'string'],
        ]);

        $next = ($dataset->versions()->max('version') ?? 0) + 1;
        $version = $this->storeVersion($dataset, $next, $data['csv'], $data['changelog'] ?? null);

        return response()->json(['version' => $version], 201);
    }

    private function storeVersion(Dataset $dataset, int $number, string $csv, ?string $changelog)
    {
        $path = "datasets/{$dataset->id}/v{$number}.csv";
        Storage::disk('local')->put($path, $csv);

        return $dataset->versions()->create([
            'version' => $number,
            'file_path' => $path,
            'changelog' => $changelog,
        ]);
    }
}
