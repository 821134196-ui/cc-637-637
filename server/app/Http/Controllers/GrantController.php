<?php

namespace App\Http\Controllers;

use App\Models\Grant;
use Illuminate\Http\Request;

class GrantController extends Controller
{
    /**
     * The researcher's approved scopes: what fields of which version they may
     * access, and until when.
     */
    public function index(Request $request)
    {
        $grants = $request->user()->grants()
            ->with(['dataset:id,title', 'version:id,version'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (Grant $g) => [
                'id' => $g->id,
                'dataset' => $g->dataset,
                'version' => $g->version,
                'fields' => $g->fields,
                'valid_from' => $g->valid_from,
                'valid_until' => $g->valid_until,
                'status' => $g->status,
                'usable' => $g->isUsable(),
            ]);

        return response()->json(['grants' => $grants]);
    }

    /**
     * Issue a download link (token) for a grant. Only the grant owner, only
     * while the grant is usable.
     */
    public function issueToken(Request $request, Grant $grant)
    {
        abort_unless($grant->user_id === $request->user()->id, 403);

        if (! $grant->isUsable()) {
            return response()->json(['message' => '授权已失效，无法生成下载链接'], 410);
        }

        $token = $grant->issueDownloadToken();

        return response()->json([
            'token' => $token->token,
            'url' => url('/api/download/'.$token->token),
        ], 201);
    }

    /**
     * Approver revokes a grant. All download tokens for it die immediately.
     */
    public function revoke(Request $request, Grant $grant)
    {
        $grant->revoke($request->user());

        return response()->json(['grant' => $grant->fresh()]);
    }
}
