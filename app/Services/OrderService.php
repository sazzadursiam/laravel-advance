<?php

namespace App\Services;

use App\Actions\Orders\CreateOrderAction;
use App\Models\Order;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class OrderService
{
    public function __construct(
        private readonly CreateOrderAction $createOrderAction
    ) {
    }

    public function paginateForUser(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = Order::query()
            ->with(['items.product'])
            ->latest();

        if (! $user->isAdmin()) {
            $query->where('user_id', $user->id);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate(
            perPage: min((int) ($filters['per_page'] ?? 15), 100)
        );
    }

    public function create(User $user, array $data): Order
    {
        return $this->createOrderAction->execute($user, $data);
    }

    public function findForUser(User $user, Order $order): Order
    {
        if (! $user->isAdmin() && $order->user_id !== $user->id) {
            abort(403, 'You are not allowed to access this order.');
        }

        return $order->load(['user', 'items.product']);
    }
}
