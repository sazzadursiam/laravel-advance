<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\GenerateOrderReportRequest;
use App\Models\GeneratedReport;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;

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

    public function generateOrderReport(GenerateOrderReportRequest $request): JsonResponse
    {
        $batch = $this->reportService->dispatchMonthlyOrderReportBatch(
            admin: $request->user(),
            year: (int) $request->integer('year'),
            months: $request->input('months', [])
        );

        return response()->json([
            'message' => 'Order report batch has been dispatched.',
            'data' => [
                'batch_id' => $batch->id,
                'name' => $batch->name,
                'total_jobs' => $batch->totalJobs,
                'pending_jobs' => $batch->pendingJobs,
                'failed_jobs' => $batch->failedJobs,
                'progress' => $batch->progress(),
            ],
        ], 202);
    }

    public function showBatch(Request $request, string $batchId): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403, 'Only admin can view report batches.');

        $batch = Bus::findBatch($batchId);

        if (! $batch) {
            return response()->json([
                'message' => 'Batch not found.',
            ], 404);
        }

        $reports = GeneratedReport::query()
            ->where('batch_id', $batchId)
            ->orderBy('period_start')
            ->get();

        return response()->json([
            'data' => [
                'batch' => [
                    'id' => $batch->id,
                    'name' => $batch->name,
                    'total_jobs' => $batch->totalJobs,
                    'pending_jobs' => $batch->pendingJobs,
                    'failed_jobs' => $batch->failedJobs,
                    'processed_jobs' => $batch->processedJobs(),
                    'progress' => $batch->progress(),
                    'finished' => $batch->finished(),
                    'cancelled' => $batch->cancelled(),
                    'created_at' => $batch->createdAt?->toDateTimeString(),
                    'finished_at' => $batch->finishedAt?->toDateTimeString(),
                ],
                'reports' => $reports,
            ],
        ]);
    }
}
