<?php
namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\RateOrderRequest;
use App\Http\Resources\OrderRatingResource;
use App\Services\RatingService;
use App\Facades\Responder;
use App\Models\Order;

class RatingController extends Controller
{
    protected RatingService $ratingService;

    public function __construct(RatingService $ratingService)
    {
        $this->ratingService = $ratingService;
    }

public function store(RateOrderRequest $request)
{
    $orderId = $request->order_id;

    // تحقق إذا تم تقييم الطلب بالفعل
    if ($this->ratingService->isOrderAlreadyRated($orderId)) {
        return response()->json([
            'status' => 402,
            'data' => null,
            'message' => __('apis.order_already_rated'),
        ], 402);
    }

    // جلب الطلب
    $order = Order::findOrFail($orderId);

    // تحقق من حالة الطلب
  if ($order->status !== 'delivered') {
    return Responder::error('لا يمكنك تقييم الطلب إلا بعد توصيله');
}

    // حفظ التقييم
    $this->ratingService->rateOrder($request->validated());

    return Responder::success(null, ['message' => __('apis.rating_submitted')]);
}



    public function myRatings()
    {
        $ratings = $this->ratingService->getUserRatings();

        return Responder::success(OrderRatingResource::collection($ratings));
    }
}
