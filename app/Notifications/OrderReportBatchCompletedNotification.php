<?php

namespace App\Notifications;

use Illuminate\Bus\Batch;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrderReportBatchCompletedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Batch $batch
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'batch_id' => $this->batch->id,
            'name' => $this->batch->name,
            'total_jobs' => $this->batch->totalJobs,
            'pending_jobs' => $this->batch->pendingJobs,
            'failed_jobs' => $this->batch->failedJobs,
            'processed_jobs' => $this->batch->processedJobs(),
            'progress' => $this->batch->progress(),
            'message' => 'Order report batch has been completed.',
        ];
    }
}
