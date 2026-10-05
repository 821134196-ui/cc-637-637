<?php

namespace App\Http\Controllers;

use App\Models\Citation;
use App\Models\Grant;
use Illuminate\Http\Request;

class CitationController extends Controller
{
    /**
     * Public registry of published results and the exact data versions used.
     * Entries remain after the underlying grant is revoked or expires.
     */
    public function index()
    {
        $citations = Citation::with(['dataset:id,title', 'version:id,version', 'user:id,name'])
            ->orderByDesc('id')
            ->get();

        return response()->json(['citations' => $citations]);
    }

    /**
     * Register a published result. The version is taken from the grant
     * server-side — it records the data actually used, not whatever the
     * client claims.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'grant_id' => ['required', 'exists:grants,id'],
            'citation_number' => ['required', 'string', 'max:255', 'unique:citations,citation_number'],
            'title' => ['required', 'string', 'max:255'],
            'venue' => ['nullable', 'string', 'max:255'],
            'published_at' => ['nullable', 'date'],
        ]);

        $grant = Grant::findOrFail($data['grant_id']);
        abort_unless($grant->user_id === $request->user()->id, 403);

        $citation = Citation::create([
            'user_id' => $request->user()->id,
            'grant_id' => $grant->id,
            'dataset_id' => $grant->dataset_id,
            'dataset_version_id' => $grant->dataset_version_id,
            'citation_number' => $data['citation_number'],
            'title' => $data['title'],
            'venue' => $data['venue'] ?? null,
            'published_at' => $data['published_at'] ?? null,
        ]);

        return response()->json(['citation' => $citation->load(['dataset:id,title', 'version:id,version'])], 201);
    }
}
