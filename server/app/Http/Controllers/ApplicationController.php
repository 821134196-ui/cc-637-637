<?php

namespace App\Http\Controllers;

use App\Models\AccessApplication;
use App\Models\Dataset;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ApplicationController extends Controller
{
    /**
     * Full field list (including restricted flags) for the application form.
     * Requires login — the public catalog never exposes restricted fields.
     */
    public function fields(Dataset $dataset)
    {
        return response()->json([
            'fields' => $dataset->fields()->get()->map(fn ($f) => [
                'name' => $f->name,
                'label' => $f->label,
                'description' => $f->description,
                'is_restricted' => $f->is_restricted,
            ]),
            'versions' => $dataset->versions()->orderByDesc('version')->get(['id', 'version', 'changelog', 'created_at']),
        ]);
    }

    public function index(Request $request)
    {
        $applications = $request->user()->applications()
            ->with(['dataset:id,title', 'version:id,version', 'grant'])
            ->orderByDesc('id')
            ->get();

        return response()->json(['applications' => $applications]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'dataset_id' => ['required', 'exists:datasets,id'],
            'dataset_version_id' => ['required', 'exists:dataset_versions,id'],
            'purpose' => ['required', 'string', 'min:10'],
            'fields' => ['required', 'array', 'min:1'],
            'fields.*' => ['string'],
            'access_start' => ['required', 'date'],
            'access_end' => ['required', 'date', 'after:access_start'],
        ]);

        $dataset = Dataset::with('fields')->findOrFail($data['dataset_id']);

        // The requested version must actually belong to this dataset.
        $version = $dataset->versions()->whereKey($data['dataset_version_id'])->first();
        if (! $version) {
            return response()->json(['message' => '所选版本不属于该数据集'], 422);
        }

        // Requested fields must be real fields of this dataset.
        $known = $dataset->fields->pluck('name')->all();
        $unknown = array_diff($data['fields'], $known);
        if ($unknown) {
            return response()->json([
                'message' => '包含不存在的字段: '.implode(', ', $unknown),
            ], 422);
        }

        $application = AccessApplication::create([
            'user_id' => $request->user()->id,
            'dataset_id' => $dataset->id,
            'dataset_version_id' => $version->id,
            'purpose' => $data['purpose'],
            'fields' => array_values(array_unique($data['fields'])),
            'access_start' => $data['access_start'],
            'access_end' => $data['access_end'],
            'status' => AccessApplication::STATUS_PENDING,
        ]);

        return response()->json(['application' => $application], 201);
    }

    // Note: there is deliberately no update endpoint. A rejected application
    // is final — a changed purpose must be submitted as a new application.
}
