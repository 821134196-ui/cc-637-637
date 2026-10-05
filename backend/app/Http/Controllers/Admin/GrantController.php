<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\NoticeMail;
use App\Models\AccessGrant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class GrantController extends Controller
{
    public function index()
    {
        $grants = AccessGrant::with(['user', 'version.dataset'])
            ->latest()
            ->get()
            ->map(fn (AccessGrant $g) => [
                'id' => $g->id,
                'status' => $g->status(),
                'granted_fields' => $g->granted_fields,
                'period_start' => $g->period_start?->toDateString(),
                'period_end' => $g->period_end?->toDateString(),
                'revoked_at' => $g->revoked_at,
                'revoke_reason' => $g->revoke_reason,
                'user' => ['id' => $g->user->id, 'name' => $g->user->name, 'email' => $g->user->email],
                'dataset' => ['id' => $g->version->dataset->id, 'name' => $g->version->dataset->name],
                'version' => ['id' => $g->version->id, 'version' => $g->version->version],
            ]);

        return response()->json($grants);
    }

    /**
     * Revoke a grant: immediately invalidates every download link issued
     * under it. Registered citations are kept untouched.
     */
    public function revoke(Request $request, AccessGrant $grant)
    {
        if ($grant->isRevoked()) {
            return response()->json(['message' => '该授权已被撤回'], 422);
        }

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($grant, $data) {
            $grant->update([
                'revoked_at' => now(),
                'revoke_reason' => $data['reason'],
            ]);
            // Kill all outstanding download links immediately.
            $grant->downloadTokens()->delete();
        });

        $grant->load(['version.dataset', 'user']);
        Mail::to($grant->user->email)->send(new NoticeMail(
            "数据访问授权 #{$grant->id} 已被撤回",
            "您对 {$grant->version->dataset->name} {$grant->version->version} 的访问授权已被撤回，"
            ."所有下载链接即刻失效。\n原因: {$data['reason']}\n已登记的成果引用不受影响。"
        ));

        return response()->json(['ok' => true, 'status' => $grant->fresh()->status()]);
    }
}
