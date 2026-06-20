<?php

namespace App\Actions\Orders;

use App\Events\OrderCreated;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Services\ReportService;

class CreateOrderAction
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly ReportService $reportService
    ) {
    }

    public function execute(User $user, array $data): Order
    {
        return DB::transaction(function () use ($user, $data) {
            $items = collect($data['items']);

            $productIds = $items->pluck('product_id')->unique()->values();

            $products = Product::query()
                ->whereIn('id', $productIds)
                ->where('is_active', true)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($products->count() !== $productIds->count()) {
                throw ValidationException::withMessages([
                    'items' => ['One or more products are inactive or unavailable.'],
                ]);
            }

            $totalAmount = 0;

            foreach ($items as $item) {
                $product = $products->get($item['product_id']);

                if ($product->stock < $item['quantity']) {
                    throw ValidationException::withMessages([
                        'items' => ["Insufficient stock for product: {$product->name}"],
                    ]);
                }

                $totalAmount += $product->price * $item['quantity'];
            }

            $order = Order::query()->create([
                'user_id' => $user->id,
                'order_number' => $this->generateOrderNumber(),
                'status' => Order::STATUS_PENDING,
                'total_amount' => $totalAmount,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($items as $item) {
                $product = $products->get($item['product_id']);

                $quantity = (int) $item['quantity'];
                $subtotal = $product->price * $quantity;

                $order->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $product->price,
                    'subtotal' => $subtotal,
                ]);

                $product->decrement('stock', $quantity);
            }

            $order->load(['user', 'items.product']);

            $this->auditLogService->log(
                action: 'order.created',
                model: $order,
                oldValues: null,
                newValues: [
                    'order' => $order->only([
                        'id',
                        'user_id',
                        'order_number',
                        'status',
                        'total_amount',
                        'notes',
                    ]),
                    'items' => $order->items->map(function ($item) {
                        return $item->only([
                            'id',
                            'product_id',
                            'quantity',
                            'unit_price',
                            'subtotal',
                        ]);
                    })->values()->toArray(),
                ],
            );

            $this->reportService->clearOrderReportCache($user);
            $this->reportService->clearOrderReportCache();

            event(new OrderCreated($order));

            return $order;
        });
    }

    private function generateOrderNumber(): string
    {
        do {
            $orderNumber = 'ORD-' . now()->format('Ymd') . '-' . Str::upper(Str::random(8));
        } while (Order::query()->where('order_number', $orderNumber)->exists());

        return $orderNumber;
    }
}
