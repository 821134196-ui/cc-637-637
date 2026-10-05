<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsApprover
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isApprover()) {
            return response()->json(['message' => '需要审批人权限'], 403);
        }

        return $next($request);
    }
}
