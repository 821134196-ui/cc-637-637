<?php

namespace App\Http\Controllers;

use App\Models\AccessApplication;
use App\Models\Grant;
use App\Notifications\ApplicationDecided;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApprovalController extends Controller
{
    public function pending()
    {
        $applications = AccessApplication::with(['user:id,name,email', 'dataset:id,title', 'version:id,version'])
            ->orderBy('id')
            ->get();

        return response()->json(['applications' => $applications]);
    }

    public function approve(Request $request, AccessApplication $application)
    {
        if (! $application->isPending()) {
            return response()->json(['message' => '该申请已处理，不能重复审批'], 422);
        }

        $grant = DB::transaction(function () use ($request, $application) {
            $application->update([
                'status' => AccessApplication::STATUS_APPROVED,
                'decided_by' => $request->user()->id,
                'decided_at' => now(),
                'decision_note' => $request->input('note'),
            ]);

            // Snapshot exactly what was approved: this version, these fields,
            // this window. Later dataset updates do not widen the grant.
            return Grant::create([
                'application_id' => $application->id,
                'user_id' => $application->user_id,
                'dataset_id' => $application->dataset_id,
                'dataset_version_id' => $application->dataset_version_id,
                'fields' => $application->fields,
                'valid_from' => $application->access_start->startOfDay(),
                'valid_until' => $application->access_end->endOfDay(),
                'status' => Grant::STATUS_ACTIVE,
            ]);
        });

        $application->user->notify(new ApplicationDecided($application, true));

        return response()->json(['application' => $application->fresh(), 'grant' => $grant]);
    }

    public function reject(Request $request, AccessApplication $application)
    {
        if (! $application->isPending()) {
            return response()->json(['message' => '该申请已处理，不能重复审批'], 422);
        }

        $application->update([
            'status' => AccessApplication::STATUS_REJECTED,
            'decided_by' => $request->user()->id,
            'decided_at' => now(),
            'decision_note' => $request->input('note'),
        ]);

        $application->user->notify(new ApplicationDecided($application, false));

        return response()->json(['application' => $application->fresh()]);
    }
}
