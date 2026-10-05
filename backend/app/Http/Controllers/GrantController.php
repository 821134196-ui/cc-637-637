<?php

namespace App\Http\Controllers;

use App\Models\AccessGrant;
use App\Models\DownloadToken;
use Illuminate\Http\Request;

class GrantController extends Controller
{
    public function index(Request $request)
    {
        $grants = $request->user()
            ->accessGrants()
            ->with(['version.dataset', 'downloadTokens'])
            ->latest()
            ->get()
            ->map(fn (AccessGrant $g) => $this->payload($g));

        return response()->json($grants);
    }

    /**
     * Issue a fresh download link for an active grant owned by the caller.
     */
    public function createLink(Request $request, AccessGrant $grant)
    {
        abort_unless($grant->user_id === $request->user()->id, 403, '无权访问该授权');

        if (! $grant->isActive()) {
            return response()->json([
                'message' => '授权已'.($grant->isRevoked() ? '被撤回' : '到期').'，无法生成下载链接',
            ], 422);
        }

        [$token, $plain] = DownloadToken::issue($grant, $request->input('label'));

        return response()->json([
            'id' => $token->id,
            'url' => "/api/download/{$plain}",
            'created_at' => $token->created_at,
        ], 201);
    }

    private function payload(AccessGrant $g): array
    {
        return [
            'id' => $g->id,
            'status' => $g->status(),
            'granted_fields' => $g->granted_fields,
            'period_start' => $g->period_start?->toDateString(),
            'period_end' => $g->period_end?->toDateString(),
            'revoked_at' => $g->revoked_at,
            'revoke_reason' => $g->revoke_reason,
            'dataset' => [
                'id' => $g->version->dataset->id,
                'name' => $g->version->dataset->name,
                'code' => $g->version->dataset->code,
            ],
            'version' => [
                'id' => $g->version->id,
                'version' => $g->version->version,
            ],
            'download_links' => $g->downloadTokens->map(fn ($t) => [
                'id' => $t->id,
                'label' => $t->label,
                'created_at' => $t->created_at,
            ])->values(),
        ];
    }
}
