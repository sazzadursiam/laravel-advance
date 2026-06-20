<?php

namespace App\Jobs;

use App\Models\GeneratedReport;
use App\Models\Order;
use App\Services\AuditLogService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GenerateOrderReportJob implements ShouldQueue
{
    use Batchable, Queueable;

    public int $tries = 3;

    public int $timeout = 180;

    public function __construct(
        private readonly int $reportId
    ) {
        $this->onQueue('reports');
    }

    public function handle(AuditLogService $auditLogService): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $report = GeneratedReport::query()->findOrFail($this->reportId);

        $report->update([
            'status' => GeneratedReport::STATUS_PROCESSING,
        ]);

        try {
            $query = Order::query()
                ->whereBetween('created_at', [
                    $report->period_start->startOfDay(),
                    $report->period_end->endOfDay(),
                ]);

            $summary = $query
                ->selectRaw('COUNT(*) as total_orders')
                ->selectRaw("SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders")
                ->selectRaw("SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing_orders")
                ->selectRaw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders")
                ->selectRaw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_orders")
                ->selectRaw('COALESCE(SUM(total_amount), 0) as total_revenue')
                ->first();

            $payload = [
                'period_start' => $report->period_start->toDateString(),
                'period_end' => $report->period_end->toDateString(),
                'total_orders' => (int) $summary->total_orders,
                'pending_orders' => (int) $summary->pending_orders,
                'processing_orders' => (int) $summary->processing_orders,
                'completed_orders' => (int) $summary->completed_orders,
                'failed_orders' => (int) $summary->failed_orders,
                'total_revenue' => (int) $summary->total_revenue,
                'total_revenue_formatted' => number_format(((int) $summary->total_revenue) / 100, 2),
            ];

            $report->update([
                'status' => GeneratedReport::STATUS_COMPLETED,
                'payload' => $payload,
                'completed_at' => now(),
            ]);

            $auditLogService->log(
                action: 'report.generated',
                model: $report,
                oldValues: null,
                newValues: $payload,
            );
        } catch (Throwable $exception) {
            $report->update([
                'status' => GeneratedReport::STATUS_FAILED,
                'error_message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
