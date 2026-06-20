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
            ->select([
                'id',
                'user_id',
                'order_number',
                'status',
                'total_amount',
                'notes',
                'processed_at',
                'created_at',
                'updated_at',
            ])
            ->with([
                'items:id,order_id,product_id,quantity,unit_price,subtotal',
                'items.product:id,name,sku,price',
            ])
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

    public function find(Order $order): Order
    {
        return $order->load(['user', 'items.product']);
    }
}
