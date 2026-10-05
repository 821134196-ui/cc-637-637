<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\NoticeMail;
use App\Models\AccessGrant;
use App\Models\AccessRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class RequestController extends Controller
{
    public function index(Request $request)
    {
        $query = AccessRequest::with(['user', 'version.dataset', 'grant'])->latest();
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return response()->json($query->get()->map(fn (AccessRequest $r) => $this->payload($r)));
    }

    /**
     * Approve a pending request. The grant snapshots the approved fields and
     * the requested period — later dataset updates cannot widen it.
     */
    public function approve(Request $request, AccessRequest $accessRequest)
    {
        if ($accessRequest->status !== AccessRequest::STATUS_PENDING) {
            return response()->json(['message' => '该申请已处理，不能重复审批'], 422);
        }

        $data = $request->validate([
            'granted_fields' => ['nullable', 'array', 'min:1'],
            'granted_fields.*' => ['string'],
        ]);

        // Admin may narrow the approved fields, but never exceed what was requested.
        $grantedFields = $data['granted_fields'] ?? $accessRequest->requested_fields;
        $outside = array_diff($grantedFields, $accessRequest->requested_fields);
        if ($outside !== []) {
            return response()->json([
                'message' => '批准字段不能超出申请范围: '.implode(', ', $outside),
            ], 422);
        }

        $grant = DB::transaction(function () use ($accessRequest, $grantedFields, $request) {
            $accessRequest->update([
                'status' => AccessRequest::STATUS_APPROVED,
                'decided_by' => $request->user()->id,
                'decided_at' => now(),
            ]);

            return AccessGrant::create([
                'access_request_id' => $accessRequest->id,
                'user_id' => $accessRequest->user_id,
                'dataset_version_id' => $accessRequest->dataset_version_id,
                'granted_fields' => array_values($grantedFields),
                'period_start' => $accessRequest->period_start,
                'period_end' => $accessRequest->period_end,
            ]);
        });

        $accessRequest->load('version.dataset');
        Mail::to($accessRequest->user->email)->send(new NoticeMail(
            "数据访问申请 #{$accessRequest->id} 已批准",
            "您对 {$accessRequest->version->dataset->name} {$accessRequest->version->version} 的访问申请已批准。\n"
            .'获批字段: '.implode(', ', $grant->granted_fields)."\n"
            ."使用期限: {$grant->period_start->toDateString()} 至 {$grant->period_end->toDateString()}"
        ));

        return response()->json($this->payload($accessRequest->fresh(['user', 'version.dataset', 'grant'])));
    }

    /**
     * Reject a pending request. Rejection is final — a changed purpose
     * requires submitting a brand-new application.
     */
    public function reject(Request $request, AccessRequest $accessRequest)
    {
        if ($accessRequest->status !== AccessRequest::STATUS_PENDING) {
            return response()->json(['message' => '该申请已处理，不能重复审批'], 422);
        }

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $accessRequest->update([
            'status' => AccessRequest::STATUS_REJECTED,
            'decided_by' => $request->user()->id,
            'decided_at' => now(),
            'decision_reason' => $data['reason'],
        ]);

        $accessRequest->load('version.dataset');
        Mail::to($accessRequest->user->email)->send(new NoticeMail(
            "数据访问申请 #{$accessRequest->id} 未获批准",
            "您对 {$accessRequest->version->dataset->name} {$accessRequest->version->version} 的访问申请未获批准。\n"
            ."原因: {$data['reason']}\n如需变更用途，请重新提交申请。"
        ));

        return response()->json($this->payload($accessRequest->fresh(['user', 'version.dataset', 'grant'])));
    }

    private function payload(AccessRequest $r): array
    {
        return [
            'id' => $r->id,
            'status' => $r->status,
            'purpose' => $r->purpose,
            'requested_fields' => $r->requested_fields,
            'period_start' => $r->period_start?->toDateString(),
            'period_end' => $r->period_end?->toDateString(),
            'decision_reason' => $r->decision_reason,
            'decided_at' => $r->decided_at,
            'created_at' => $r->created_at,
            'applicant' => [
                'id' => $r->user->id,
                'name' => $r->user->name,
                'email' => $r->user->email,
            ],
            'dataset' => [
                'id' => $r->version->dataset->id,
                'name' => $r->version->dataset->name,
                'code' => $r->version->dataset->code,
            ],
            'version' => [
                'id' => $r->version->id,
                'version' => $r->version->version,
            ],
            'grant_id' => $r->grant?->id,
            'grant_status' => $r->grant?->status(),
        ];
    }
}
