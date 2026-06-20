<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reportService
    ) {
    }

    public function orderSummary(Request $request): JsonResponse
    {
        abort_unless($request->user()->tokenCan('reports:view'), 403, 'You are not allowed to view reports.');

        $summary = $this->reportService->orderSummaryWithLock($request->user());

        return response()->json([
            'data' => $summary,
        ]);
    }

    public function generateOrderReport(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Order report batch generation will be implemented later.',
        ]);
    }
}
