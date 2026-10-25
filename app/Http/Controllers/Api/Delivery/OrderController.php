<?php

namespace App\Http\Controllers\Api\Delivery;

use App\Facades\Responder;
use App\Http\Resources\Api\Client\OrderDetailsResource;
use App\Http\Resources\Api\Order\OrderdeliveyIndexResource;
use App\Http\Resources\Api\Order\OrderResource;
use App\Models\Order;

use App\Services\DeliveryStatisticsService;
use App\Services\OrderService;
use App\Services\Order\OrderQueryService;
use App\Services\Order\OrderStatusService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Order\CancelDeliveryOrderRequest;
use App\Http\Requests\Api\Order\UpdateOrderStatusRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
    protected $queryService;
    protected $statusService;

    public function __construct(
        OrderQueryService $queryService,
        OrderStatusService $statusService
    ) {
        $this->queryService = $queryService;
        $this->statusService = $statusService;
    }

    /**
     * Get delivery person's orders
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        if ($user->type !== 'delivery') {
            return Responder::error(__('apis.must_be_delivery'), 403);
        }

        $orders = $this->queryService->getDeliveryOrders(
            $request->get('status'),
            $request->get('search')
        );

        // Use unified OrderResource
        return Responder::success(OrderdeliveyIndexResource::collection($orders));
    }

    /**
     * Show delivery order details
     */
    public function show($id)
    {
        $user = auth()->user();
        if ($user->type !== 'delivery') {
            return Responder::error(__('apis.must_be_delivery'), 403);
        }

        $order = $this->queryService->getDeliveryOrder($id);

        if (!$order) {
            return Responder::error(__('apis.order_not_found'), 404);
        }

        return Responder::success(new OrderDetailsResource($order));
    }

    /**
     * Update order status (delivery person)
     */
    public function updateStatus(UpdateOrderStatusRequest $request, $id)
    {
        $user = auth()->user();
        if ($user->type !== 'delivery') {
            return Responder::error(__('apis.must_be_delivery'), 403);
        }

        try {
            $validated = $request->validated();
            $order = $this->statusService->updateDeliveryOrderStatus($id, $validated['status']);
        } catch (\Exception $e) {
            return Responder::error($e->getMessage(), 422);
        }

        if ($order === null) {
            return Responder::error(__('apis.order_not_found'), [], 404);
        }

        if ($order === false) {
            return Responder::error(__('apis.invalid_status_update'), 422);
        }

        return Responder::success([
            'message' => __('apis.order_status_updated'),
            'order'   => new OrderResource($order),
        ]);
    }

    /**
     * Cancel an assigned order (delivery driver)
     *
     * Removes the delivery assignment and returns the order to pending pool.
     */
    public function cancel(CancelDeliveryOrderRequest $request, $id)
    {
        $user = auth()->user();
        if ($user->type !== 'delivery') {
            return Responder::error(__('apis.must_be_delivery'), 403);
        }

        try {
            $validated = $request->validated();
            $order = $this->statusService->cancelDeliveryOrder($id, $validated['cancel_reason']);
        } catch (\Exception $e) {
            return Responder::error($e->getMessage(), 422);
        }

        if ($order === null) {
            return Responder::error(__('apis.order_not_found'), 404);
        }

        return Responder::success([
            'message' => __('apis.order_cancelled'),
            'order'   => new OrderResource($order),
        ]);
    }


public function statistics()
{
    $user = auth()->user();
    if ($user->type !== 'delivery') {
        return Responder::error(__('apis.must_be_delivery'), 403);
    }

    try {
        $service = new DeliveryStatisticsService($user->id);
        $data = $service->getDashboardData();

        return Responder::success($data, 'Dashboard loaded successfully');
    } catch (\Exception $e) {
        return Responder::error($e->getMessage(), 500);
    }
}



}

