<?php

namespace App\Services;

use App\Jobs\GenerateOrderReportJob;
use App\Models\GeneratedReport;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderReportBatchCompletedNotification;
use Carbon\Carbon;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Throwable;

class ReportService
{
    public function orderSummary(User $user): array
    {
        $cacheKey = $this->orderSummaryCacheKey($user);

        return Cache::tags($this->orderReportTags($user))
            ->remember($cacheKey, now()->addMinutes(10), function () use ($user) {
                return $this->buildOrderSummary($user);
            });
    }

    public function orderSummaryWithLock(User $user): array
    {
        $cacheKey = $this->orderSummaryCacheKey($user);
        $tags = $this->orderReportTags($user);

        $cached = Cache::tags($tags)->get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $lock = Cache::lock("report-lock:{$cacheKey}", 30);

        return $lock->block(10, function () use ($user, $cacheKey, $tags) {
            $cached = Cache::tags($tags)->get($cacheKey);

            if ($cached !== null) {
                return $cached;
            }

            $summary = $this->buildOrderSummary($user);

            Cache::tags($tags)->put($cacheKey, $summary, now()->addMinutes(10));

            return $summary;
        });
    }

    public function clearOrderReportCache(?User $user = null): void
    {
        if ($user) {
            Cache::tags($this->orderReportTags($user))->flush();
            return;
        }

        Cache::tags(['orders', 'reports'])->flush();
    }

    private function buildOrderSummary(User $user): array
    {
        $query = Order::query();

        if (! $user->isAdmin()) {
            $query->where('user_id', $user->id);
        }

        $summary = $query
            ->selectRaw('COUNT(*) as total_orders')
            ->selectRaw("SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders")
            ->selectRaw("SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing_orders")
            ->selectRaw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders")
            ->selectRaw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_orders")
            ->selectRaw('COALESCE(SUM(total_amount), 0) as total_revenue')
            ->first();

        return [
            'total_orders' => (int) $summary->total_orders,
            'pending_orders' => (int) $summary->pending_orders,
            'processing_orders' => (int) $summary->processing_orders,
            'completed_orders' => (int) $summary->completed_orders,
            'failed_orders' => (int) $summary->failed_orders,
            'total_revenue' => (int) $summary->total_revenue,
            'total_revenue_formatted' => number_format(((int) $summary->total_revenue) / 100, 2),
            'cached_at' => now()->toDateTimeString(),
        ];
    }

    private function orderSummaryCacheKey(User $user): string
    {
        if ($user->isAdmin()) {
            return 'reports:orders:summary:admin';
        }

        return "reports:orders:summary:user:{$user->id}";
    }

    private function orderReportTags(User $user): array
    {
        if ($user->isAdmin()) {
            return ['orders', 'reports', 'admin-reports'];
        }

        return ['orders', 'reports', "user:{$user->id}:reports"];
    }

    public function dispatchMonthlyOrderReportBatch(User $admin, int $year, array $months = []): Batch
    {
        $months = $months ?: range(1, 12);

        $jobs = [];
        $reportIds = [];

        foreach ($months as $month) {
            $start = Carbon::create($year, $month, 1)->startOfMonth();
            $end = $start->copy()->endOfMonth();

            $report = GeneratedReport::query()->create([
                'user_id' => $admin->id,
                'type' => GeneratedReport::TYPE_ORDER_MONTHLY,
                'status' => GeneratedReport::STATUS_PENDING,
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
            ]);

            $reportIds[] = $report->id;
            $jobs[] = new GenerateOrderReportJob($report->id);
        }

        $batch = Bus::batch($jobs)
            ->name("Monthly Order Report {$year}")
            ->onQueue('reports')
            ->allowFailures()
            ->then(function (Batch $batch) use ($admin) {
                $admin->notify(new OrderReportBatchCompletedNotification($batch));
            })
            ->catch(function (Batch $batch, Throwable $e) {
                logger()->error('Order report batch failed.', [
                    'batch_id' => $batch->id,
                    'error' => $e->getMessage(),
                ]);
            })
            ->finally(function (Batch $batch) {
                logger()->info('Order report batch finished.', [
                    'batch_id' => $batch->id,
                    'progress' => $batch->progress(),
                    'failed_jobs' => $batch->failedJobs,
                ]);
            })
            ->dispatch();

        GeneratedReport::query()
            ->whereIn('id', $reportIds)
            ->update([
                'batch_id' => $batch->id,
            ]);

        return $batch;
    }
    private function extractReportId(GenerateOrderReportJob $job): ?int
    {
        $reflection = new \ReflectionClass($job);

        if (! $reflection->hasProperty('reportId')) {
            return null;
        }

        $property = $reflection->getProperty('reportId');
        $property->setAccessible(true);

        return $property->getValue($job);
    }
}
