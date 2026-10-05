<?php

namespace App\Http\Controllers;

use App\Models\DatasetVersion;

/**
 * Authenticated field schema. Researchers need the full field list (names and
 * descriptions, including restricted ones) to compose an access application.
 * Metadata only — never data rows.
 */
class SchemaController extends Controller
{
    public function show(DatasetVersion $version)
    {
        $version->load('fields', 'dataset');

        return response()->json([
            'version_id' => $version->id,
            'version' => $version->version,
            'dataset' => [
                'id' => $version->dataset->id,
                'name' => $version->dataset->name,
                'code' => $version->dataset->code,
            ],
            'fields' => $version->fields->map(fn ($f) => [
                'name' => $f->name,
                'label' => $f->label,
                'data_type' => $f->data_type,
                'is_restricted' => $f->is_restricted,
                'description' => $f->description,
            ])->values(),
        ]);
    }
}
