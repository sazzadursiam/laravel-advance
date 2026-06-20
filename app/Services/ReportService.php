<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

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
}
