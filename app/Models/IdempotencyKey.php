<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdempotencyKey extends Model
{
    protected $fillable = [
        'user_id',
        'key',
        'method',
        'path',
        'request_hash',
        'response_body',
        'status_code',
        'locked_until',
        'expires_at',
    ];

    protected $casts = [
        'response_body' => 'array',
        'locked_until' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
