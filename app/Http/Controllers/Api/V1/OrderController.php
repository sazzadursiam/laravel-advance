<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Orders\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService
    ) {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $orders = $this->orderService->paginateForUser(
            $request->user(),
            $request->only(['status', 'per_page'])
        );

        return OrderResource::collection($orders);
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $order = $this->orderService->create(
            $request->user(),
            $request->validated()
        );

        return response()->json([
            'message' => 'Order created successfully.',
            'data' => new OrderResource($order),
        ], 201);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        $order = $this->orderService->findForUser($request->user(), $order);

        return response()->json([
            'data' => new OrderResource($order),
        ]);
    }

    public function update(Request $request, Order $order): JsonResponse
    {
        return response()->json([
            'message' => 'Order update will be implemented later.',
        ]);
    }

    public function destroy(Request $request, Order $order): JsonResponse
    {
        return response()->json([
            'message' => 'Order delete will be implemented later.',
        ]);
    }
}
