<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Order\RateDeliveryRequest;
use App\Services\DeliveryRateService;
use App\Services\Responder;

class DeliveryRateController extends Controller
{
    public function __construct(private DeliveryRateService $deliveryRateService)
    {
    }

    public function store(RateDeliveryRequest $request)
    {
        try {
            $rating = $this->deliveryRateService->create(auth()->user(), $request->validated());

            return Responder::success($rating, ['message' => __('apis.delivery_rating_submitted_successfully')]);
        } catch (\Exception $e) {
            return Responder::error($e->getMessage(), [], 422);
        }
    }
}
