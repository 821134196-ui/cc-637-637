<?php

namespace App\Http\Controllers;

use App\Models\Dataset;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    /**
     * Public catalog: dataset metadata + PUBLIC fields only.
     * Restricted fields (names and values) never leave the server here.
     */
    public function index()
    {
        $datasets = Dataset::with(['publicFields', 'versions'])->get()->map(fn (Dataset $d) => [
            'id' => $d->id,
            'title' => $d->title,
            'description' => $d->description,
            'department' => $d->department,
            'latest_version' => $d->latestVersion()?->version,
            'public_fields' => $d->publicFields->map(fn ($f) => [
                'name' => $f->name,
                'label' => $f->label,
                'description' => $f->description,
            ])->values(),
        ]);

        return response()->json(['datasets' => $datasets]);
    }

    /**
     * Public preview: first rows of the latest version with restricted
     * columns stripped out.
     */
    public function show(Dataset $dataset)
    {
        $dataset->load('publicFields');
        $version = $dataset->latestVersion();

        $publicNames = $dataset->publicFields->pluck('name')->all();

        $preview = [];
        if ($version) {
            $csv = $version->readCsv();
            foreach (array_slice($csv['rows'], 0, 10) as $row) {
                $preview[] = array_intersect_key($row, array_flip($publicNames));
            }
        }

        return response()->json([
            'dataset' => [
                'id' => $dataset->id,
                'title' => $dataset->title,
                'description' => $dataset->description,
                'department' => $dataset->department,
                'latest_version' => $version?->version,
                'public_fields' => $dataset->publicFields->map(fn ($f) => [
                    'name' => $f->name,
                    'label' => $f->label,
                    'description' => $f->description,
                ])->values(),
            ],
            'preview' => $preview,
        ]);
    }
}
