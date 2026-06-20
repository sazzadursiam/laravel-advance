<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeneratedReport extends Model
{
    public const TYPE_ORDER_MONTHLY = 'order_monthly';

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'user_id',
        'batch_id',
        'type',
        'status',
        'period_start',
        'period_end',
        'payload',
        'error_message',
        'completed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'period_start' => 'date',
        'period_end' => 'date',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
