<?php

namespace App\Http\Controllers;

use App\Mail\NoticeMail;
use App\Models\AccessRequest;
use App\Models\DatasetVersion;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class AccessRequestController extends Controller
{
    public function index(Request $request)
    {
        $requests = $request->user()
            ->accessRequests()
            ->with(['version.dataset', 'grant'])
            ->latest()
            ->get()
            ->map(fn (AccessRequest $r) => $this->payload($r));

        return response()->json($requests);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'dataset_version_id' => ['required', 'integer', Rule::exists('dataset_versions', 'id')],
            'purpose' => ['required', 'string', 'min:10', 'max:2000'],
            'requested_fields' => ['required', 'array', 'min:1'],
            'requested_fields.*' => ['string'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after:period_start'],
        ]);

        $version = DatasetVersion::with('fields')->findOrFail($data['dataset_version_id']);
        $knownFields = $version->fieldNames();
        $unknown = array_diff($data['requested_fields'], $knownFields);
        if ($unknown !== []) {
            return response()->json([
                'message' => '申请的字段在该版本中不存在: '.implode(', ', $unknown),
                'errors' => ['requested_fields' => ['未知字段: '.implode(', ', $unknown)]],
            ], 422);
        }

        $accessRequest = AccessRequest::create([
            'user_id' => $request->user()->id,
            'dataset_version_id' => $version->id,
            'purpose' => $data['purpose'],
            'requested_fields' => array_values($data['requested_fields']),
            'period_start' => $data['period_start'],
            'period_end' => $data['period_end'],
            'status' => AccessRequest::STATUS_PENDING,
        ]);

        foreach (User::where('role', User::ROLE_ADMIN)->get() as $admin) {
            Mail::to($admin->email)->send(new NoticeMail(
                "新的数据访问申请 #{$accessRequest->id}",
                "用户 {$request->user()->name} 申请访问数据集 {$version->dataset->name} {$version->version}，"
                .'字段: '.implode(', ', $accessRequest->requested_fields)."。请登录管理后台审批。"
            ));
        }

        return response()->json($this->payload($accessRequest->load(['version.dataset', 'grant'])), 201);
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
