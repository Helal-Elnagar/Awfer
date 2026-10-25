<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\GiftService;
use App\Facades\Responder;

class GiftController extends Controller
{
    protected $giftService;

    public function __construct(GiftService $giftService)
    {
        $this->giftService = $giftService;
    }

    public function index(Request $request)
    {
        $user = $request->user();

        try {
            $data = $this->giftService->processUserOrder($user);

            return Responder::success($data, ['message' => __('apis.gift_successfully')]);
        } catch (\Exception $e) {
            return Responder::error('Failed to process gifts', ['error' => $e->getMessage()], 500);
        }
    }
}
