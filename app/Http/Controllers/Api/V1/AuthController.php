<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Services\AuditLogService;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::query()
            ->where('email', $data['email'])
            ->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid email or password.'],
            ]);
        }

        $token = $user->createToken(
            $data['device_name'] ?? 'api-client',
            $this->abilitiesFor($user)
        )->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                ],
                'token_type' => 'Bearer',
                'access_token' => $token,
            ],
        ]);
    }

    public function logout(): JsonResponse
    {
        request()->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout successful.',
        ]);
    }

    public function logoutAll(AuditLogService $auditLogService): JsonResponse
    {
        $user = request()->user();

        $auditLogService->log(
            action: 'auth.logout_all',
            model: $user,
            oldValues: null,
            newValues: [
                'message' => 'All tokens revoked.',
            ],
        );

        $user->tokens()->delete();

        return response()->json([
            'message' => 'Logged out from all devices successfully.',
        ]);
    }

    private function abilitiesFor(User $user): array
    {
        if ($user->isAdmin()) {
            return ['*'];
        }

        return [
            'orders:view',
            'orders:create',
            'reports:view',
        ];
    }

    public function tokens(): JsonResponse
    {
        $tokens = request()->user()
            ->tokens()
            ->latest()
            ->get()
            ->map(function ($token) {
                return [
                    'id' => $token->id,
                    'name' => $token->name,
                    'abilities' => $token->abilities,
                    'last_used_at' => $token->last_used_at?->toDateTimeString(),
                    'created_at' => $token->created_at?->toDateTimeString(),
                ];
            });

        return response()->json([
            'data' => $tokens,
        ]);
    }

    public function revokeToken(int $tokenId, AuditLogService $auditLogService): JsonResponse
    {
        $user = request()->user();

        $token = $user->tokens()
            ->where('id', $tokenId)
            ->firstOrFail();

        $auditLogService->log(
            action: 'auth.token_revoked',
            model: $user,
            oldValues: [
                'token_id' => $token->id,
                'token_name' => $token->name,
                'abilities' => $token->abilities,
            ],
            newValues: null,
        );

        $token->delete();

        return response()->json([
            'message' => 'Token revoked successfully.',
        ]);
    }
}
