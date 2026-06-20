<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403, 'Only admin can view audit logs.');

        $logs = AuditLog::query()
            ->with('user:id,name,email')
            ->latest()
            ->paginate(
                perPage: min((int) $request->integer('per_page', 15), 100)
            );

        return response()->json([
            'data' => $logs,
        ]);
    }
}
