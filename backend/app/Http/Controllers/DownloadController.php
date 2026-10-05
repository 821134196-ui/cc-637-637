<?php

namespace App\Http\Controllers;

use App\Models\DownloadToken;
use App\Support\CsvReader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Token-based dataset download.
 *
 * Security invariants enforced here:
 *  - the dataset VERSION always comes from the grant, never from the request;
 *  - the returned COLUMNS are always intersected with the grant's field
 *    snapshot — passing extra ?fields= can never widen the result;
 *  - revoked or expired grants (and their deleted tokens) make old links fail.
 */
class DownloadController extends Controller
{
    public function download(Request $request, string $token)
    {
        $downloadToken = DownloadToken::with('grant.version.dataset')
            ->where('token_hash', DownloadToken::hashToken($token))
            ->first();

        if (! $downloadToken) {
            return response()->json(['message' => '下载链接无效或已被撤销'], 404);
        }

        if ($downloadToken->expires_at !== null && $downloadToken->expires_at->isPast()) {
            return response()->json(['message' => '下载链接已过期'], 410);
        }

        $grant = $downloadToken->grant;
        if ($grant->isRevoked()) {
            return response()->json(['message' => '授权已被撤回，下载链接失效'], 410);
        }
        if ($grant->isExpired()) {
            return response()->json(['message' => '授权使用期限已过，下载链接失效'], 410);
        }

        $grantedFields = $grant->granted_fields ?? [];

        // Optional narrowing: caller may ask for a subset of granted fields.
        // Accepts both ?fields=a,b and ?fields[]=a&fields[]=b — anything
        // outside the grant is rejected, never silently ignored.
        $requested = $request->query('fields');
        if (is_array($requested)) {
            $requestedFields = array_values(array_filter(array_map('trim', $requested)));
        } elseif (is_string($requested) && $requested !== '') {
            $requestedFields = array_values(array_filter(array_map('trim', explode(',', $requested))));
        } else {
            $requestedFields = [];
        }

        if ($requestedFields !== []) {
            $outside = array_diff($requestedFields, $grantedFields);
            if ($outside !== []) {
                return response()->json([
                    'message' => '请求的字段超出授权范围: '.implode(', ', $outside),
                ], 403);
            }
            $columns = $requestedFields;
        } else {
            $columns = $grantedFields;
        }

        $version = $grant->version; // pinned at approval time — never from input
        $data = CsvReader::read(Storage::path($version->file_path), $columns);
        $csv = CsvReader::toCsv($data['header'], $data['rows']);

        $filename = sprintf('%s-%s.csv', $version->dataset->code, $version->version);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'X-Dataset-Version' => $version->version,
        ]);
    }
}
