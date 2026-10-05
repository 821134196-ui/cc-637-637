<?php

namespace App\Http\Controllers;

use App\Models\DownloadToken;
use Illuminate\Http\Request;

class DownloadController extends Controller
{
    /**
     * Token-gated CSV download.
     *
     * Enforcement, all server-side:
     *  - the token must map to a grant that is still usable (active, inside
     *    its validity window) — revocation/expiry kill links immediately;
     *  - the served version is the grant's pinned version, never the latest;
     *  - the served columns are the grant's approved fields. A `fields`
     *    query parameter may only *narrow* the selection; asking for a
     *    field outside the grant is rejected with 403.
     */
    public function show(Request $request, string $token)
    {
        $downloadToken = DownloadToken::with('grant.version')->where('token', $token)->first();

        if (! $downloadToken || ! $downloadToken->grant->isUsable()) {
            return response()->json(['message' => '下载链接已失效'], 410);
        }

        $grant = $downloadToken->grant;
        $approved = $grant->fields;

        $requested = $approved;
        if ($request->filled('fields')) {
            $requested = array_values(array_filter(array_map('trim', explode(',', $request->query('fields')))));
            $outside = array_diff($requested, $approved);
            if ($outside) {
                return response()->json([
                    'message' => '请求了未获准的字段: '.implode(', ', $outside),
                ], 403);
            }
            if (! $requested) {
                return response()->json(['message' => '未指定字段'], 422);
            }
        }

        $csv = $grant->version->readCsv();

        $lines = [implode(',', $requested)];
        foreach ($csv['rows'] as $row) {
            $lines[] = implode(',', array_map(
                fn ($f) => $this->csvCell($row[$f] ?? ''),
                $requested
            ));
        }

        $filename = sprintf('dataset-%d-v%d.csv', $grant->dataset_id, $grant->version->version);

        return response(implode("\n", $lines)."\n", 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function csvCell(string $value): string
    {
        if (str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n")) {
            return '"'.str_replace('"', '""', $value).'"';
        }

        return $value;
    }
}
