<?php

namespace App\Services;

use App\Models\IdempotencyKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class IdempotencyService
{
    public function requestHash(Request $request): string
    {
        return hash('sha256', json_encode([
            'body' => $request->all(),
            'query' => $request->query(),
        ]));
    }

    public function findReusableResponse(
        ?int $userId,
        string $key,
        string $method,
        string $path,
        string $requestHash
    ): ?IdempotencyKey {
        $record = IdempotencyKey::query()
            ->where('key', $key)
            ->first();

        if (! $record) {
            return null;
        }

        if ($record->user_id !== $userId) {
            abort(409, 'This idempotency key belongs to another user.');
        }

        if ($record->method !== $method || $record->path !== $path) {
            abort(409, 'This idempotency key was used for a different endpoint.');
        }

        if ($record->request_hash !== $requestHash) {
            abort(409, 'This idempotency key was used with a different request body.');
        }

        if ($record->response_body && $record->status_code) {
            return $record;
        }

        abort(409, 'Request with this idempotency key is already being processed.');
    }

    public function reserve(
        ?int $userId,
        string $key,
        string $method,
        string $path,
        string $requestHash
    ): IdempotencyKey {
        $lock = Cache::lock("idempotency-key:{$key}", 10);

        return $lock->block(5, function () use ($userId, $key, $method, $path, $requestHash) {
            $existing = IdempotencyKey::query()
                ->where('key', $key)
                ->first();

            if ($existing) {
                abort(409, 'Request with this idempotency key is already being processed.');
            }

            return IdempotencyKey::query()->create([
                'user_id' => $userId,
                'key' => $key,
                'method' => $method,
                'path' => $path,
                'request_hash' => $requestHash,
                'locked_until' => now()->addMinutes(5),
                'expires_at' => now()->addDay(),
            ]);
        });
    }

    public function storeResponse(
        IdempotencyKey $idempotencyKey,
        array $responseBody,
        int $statusCode
    ): void {
        $idempotencyKey->update([
            'response_body' => $responseBody,
            'status_code' => $statusCode,
            'locked_until' => null,
        ]);
    }
}
