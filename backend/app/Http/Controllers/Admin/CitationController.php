<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Citation;

class CitationController extends Controller
{
    public function index()
    {
        $citations = Citation::with(['user', 'version.dataset', 'grant'])
            ->latest()
            ->get()
            ->map(fn (Citation $c) => [
                'id' => $c->id,
                'citation_number' => $c->citation_number,
                'title' => $c->title,
                'venue' => $c->venue,
                'url' => $c->url,
                'published_at' => $c->published_at?->toDateString(),
                'user' => ['id' => $c->user->id, 'name' => $c->user->name, 'email' => $c->user->email],
                'dataset' => ['id' => $c->version->dataset->id, 'name' => $c->version->dataset->name],
                'version' => ['id' => $c->version->id, 'version' => $c->version->version],
                'grant_status' => $c->grant?->status(),
            ]);

        return response()->json($citations);
    }
}
