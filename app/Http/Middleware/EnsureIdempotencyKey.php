<?php

namespace App\Http\Middleware;

use App\Services\IdempotencyService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIdempotencyKey
{
    public function __construct(
        private readonly IdempotencyService $idempotencyService
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }
        $key = $request->header('Idempotency-Key');

        if (! $key) {
            return response()->json([
                'message' => 'Idempotency-Key header is required.',
            ], 422);
        }

        if (strlen($key) > 100) {
            return response()->json([
                'message' => 'Idempotency-Key must not be greater than 100 characters.',
            ], 422);
        }

        $requestHash = $this->idempotencyService->requestHash($request);

        $existing = $this->idempotencyService->findReusableResponse(
            userId: $request->user()?->id,
            key: $key,
            method: $request->method(),
            path: $request->path(),
            requestHash: $requestHash
        );

        if ($existing) {
            return response()->json(
                $existing->response_body,
                $existing->status_code,
                [
                    'Idempotency-Replayed' => 'true',
                ]
            );
        }

        $idempotencyRecord = $this->idempotencyService->reserve(
            userId: $request->user()?->id,
            key: $key,
            method: $request->method(),
            path: $request->path(),
            requestHash: $requestHash
        );

        $response = $next($request);

        if ($response instanceof JsonResponse && $response->getStatusCode() < 500) {
            $this->idempotencyService->storeResponse(
                idempotencyKey: $idempotencyRecord,
                responseBody: json_decode($response->getContent(), true),
                statusCode: $response->getStatusCode()
            );
        }

        return $response;
    }
}
