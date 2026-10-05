<?php

namespace App\Http\Controllers;

use App\Models\AccessGrant;
use App\Models\Citation;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CitationController extends Controller
{
    public function index(Request $request)
    {
        $citations = $request->user()
            ->citations()
            ->with(['version.dataset', 'grant'])
            ->latest()
            ->get()
            ->map(fn (Citation $c) => $this->payload($c));

        return response()->json($citations);
    }

    /**
     * Register a published result. The dataset version is taken from the
     * grant (the version actually downloaded), not from user input, so the
     * citation is always linked to the version that was really used.
     * Registration stays possible after the grant expired or was revoked.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'access_grant_id' => ['required', 'integer', Rule::exists('access_grants', 'id')],
            'title' => ['required', 'string', 'max:500'],
            'venue' => ['nullable', 'string', 'max:255'],
            'url' => ['nullable', 'url', 'max:1000'],
            'published_at' => ['required', 'date'],
        ]);

        $grant = AccessGrant::with('version.dataset')->findOrFail($data['access_grant_id']);
        abort_unless($grant->user_id === $request->user()->id, 403, '只能登记本人授权下产生的成果');

        $citation = Citation::create([
            'citation_number' => Citation::nextNumber(),
            'user_id' => $request->user()->id,
            'access_grant_id' => $grant->id,
            'dataset_version_id' => $grant->dataset_version_id,
            'title' => $data['title'],
            'venue' => $data['venue'] ?? null,
            'url' => $data['url'] ?? null,
            'published_at' => $data['published_at'],
        ]);

        return response()->json($this->payload($citation->load(['version.dataset', 'grant'])), 201);
    }

    private function payload(Citation $c): array
    {
        return [
            'id' => $c->id,
            'citation_number' => $c->citation_number,
            'title' => $c->title,
            'venue' => $c->venue,
            'url' => $c->url,
            'published_at' => $c->published_at?->toDateString(),
            'dataset' => [
                'id' => $c->version->dataset->id,
                'name' => $c->version->dataset->name,
                'code' => $c->version->dataset->code,
            ],
            'version' => [
                'id' => $c->version->id,
                'version' => $c->version->version,
            ],
            'grant_status' => $c->grant?->status(),
            'created_at' => $c->created_at,
        ];
    }
}
