<?php
namespace App\Http\Controllers\Api\Client;

use App\Http\Requests\Api\Order\ReportOrderProblemRequest;
use App\Http\Resources\Api\Client\BranchResource;
use App\Http\Resources\Api\Client\LoyalityPointResource;
use App\Models\Order;
use App\Models\Problem;
use App\Models\Address;
use App\Models\Branch;
use App\Services\Responder;
use App\Traits\ResponseTrait;
use App\Services\OrderService;
use App\Services\Order\OrderCheckoutService;
use App\Services\Order\OrderQueryService;
use App\Services\Order\OrderStatusService;
use App\Services\Order\BranchLocationService;
use App\Services\Order\DeliveryCalculationService;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Http\Resources\Api\Order\OrderResource;
use App\Http\Requests\Api\Order\CancelOrderRequest;
use App\Http\Requests\Api\Order\CreateOrderRequest;
use App\Http\Resources\Api\Client\OrderDetailsResource;
use App\Http\Resources\Api\Client\ClientPaymentResource;
use App\Notifications\OrderProblemReportedNotification;
use App\Http\Requests\CreateRefundRequest;
use Barryvdh\DomPDF\Facade\Pdf as DomPdfFacade;
use Illuminate\Http\Request;
use niklasravnsborg\LaravelPdf\Facades\Pdf as FacadesPdf;

class OrderController extends Controller
{
    use ResponseTrait;

    /**
     * @var OrderService
     */
    protected $orderService;

    /**
     * OrderController constructor.
     *
     * @param OrderService $orderService
     */
    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    /**
     * Get user orders
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getOrders()
    {
        $user = auth()->user();

        // For client orders, out-for-delivery is the same stage as confirmed
        $status = request('status');
        if ($status === 'out-for-delivery') {
            $status = 'confirmed';
        }

        $filters = [
            'status' => $status,
            'sort_by' => request('sort_by'), // Use sort_by instead of sort
        ];
        $orders = $this->orderService->getUserOrders($user, $filters);

        return Responder::success(OrderResource::collection($orders));
    }

    /**
     * Get order details
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getOrder($id)
    {
        $user = auth()->user();
        $order = $this->orderService->getUserOrder($user, $id);

        if (!$order) {
            return Responder::error(__('apis.order_not_found'), [], 404);
        }

        return Responder::success(new OrderDetailsResource($order));
    }

    /**
     * Create a new order from the cart
     *
     * @param \App\Http\Requests\Api\Order\CreateOrderRequest $request
     * @return \Illuminate\Http\JsonResponse
     */

     public function createOrder(CreateOrderRequest $request)
    {
        try {
            $user   = auth()->user();
            $result = $this->orderService->createOrderFromCart($user, $request->validated());

            // Check if the result is an error response from coupon validation
            if (is_array($result) && isset($result['status']) && $result['status'] === 'error') {
                return Responder::error($result['message'], [], 422);
            }

            // If payment URL is present, return it with the order
            if (isset($result['payment_url'])) {
                return Responder::success([
                    'message' => __('apis.order_created'),
                    'payment_url' => $result['payment_url'],
                    'order' => $result['order']
                ]);
            }

            // For COD orders or if no payment URL
            return Responder::success([
                'message' => __('apis.order_created'),
                'order' => $result['order']
            ]);
        } catch (\Exception $e) {
            return Responder::error($e->getMessage(), [], 422);
        }
    }

public function reportProblem(ReportOrderProblemRequest $request)
{
    try {
        $user = auth()->user();

        $order = Order::where('id', $request->order_id)
                      ->where('user_id', $user->id)
                      ->firstOrFail();

        if ($order->status === 'problem') {
            return Responder::error(__('apis.problem_already_reported'), [], 400);
        }

        $order->update([
            'status' => 'problem',
            'problem_id' => $request->problem_id,
            'notes' => $request->notes,
        ]);

        $this->notifyAdminsOfProblem($order);

        return Responder::success(null, ['message' => __('apis.problem_reported_successfully')]);

        
    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {

        return Responder::error(__('apis.order_not_found'), [], 404);
    } catch (\Exception $e) {

        return Responder::error(__('apis.failed_to_report_problem'), [], 500);
    }
}
   
    /**
     * Cancel an order
     *
     * @param \App\Http\Requests\Api\Order\CancelOrderRequest $request
     * @return \Illuminate\Http\JsonResponse
     */

     public function SendCancelRequest(CancelOrderRequest $request)
     {
        try {
            $user = auth()->user();
            $order = Order::where('id', $request->order_id)
                          ->where('user_id', $user->id)
                          ->firstOrFail();

            // Check if order can be cancelled
            if (!in_array($order->status, ['processing', 'pending'])) {
                return Responder::error(__('apis.order_cannot_be_cancelled_at_this_stage'), [], 400);
            }

            // Check if order is already requested for cancellation
            if ($order->status === 'request_cancel') {
                return Responder::error(__('apis.cancellation_request_already_submitted'), [], 400);
            }

            // Update order status to request_cancel
            $order->update([
                'status' => 'request_cancel',
                'notes' => $request->notes,
                'cancel_reason_id' => $request->cancel_reason_id,
            ]);

            // Send notification to all admins about cancellation request
            $this->sendOrderCancellationNotificationToAdmins($order->id);

            return Responder::success(null, ['message' => __('apis.cancellation_request_submitted_successfully')]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return Responder::error(__('apis.order_not_found'), [], 404);
        } catch (\Exception $e) {
            return Responder::error(__('apis.failed_to_submit_cancellation_request'), [], 500);
        }
     }
    public function cancelOrder(CancelOrderRequest $request)
    {
        try {
            $user      = auth()->user();
            $validated = $request->validated();
            $order = $user->orders()->findOrFail($validated['order_id']);

            $cancelledOrder = $this->orderService->cancelOrder($order, $validated['reason'] ?? null, auth()->user());

            return Responder::success(new OrderResource($cancelledOrder), ['message' => __('apis.order_canceled')]);
        } catch (\Exception $e) {
            return Responder::error($e->getMessage(), [], 422);
        }
    }

    public function payments()
    {
       $paymnets =  $this->orderService->ClientOnlinePayments()->get();
        return Responder::success(ClientPaymentResource::collection($paymnets));

    }

    public function loyalityPoints()
    {
        if(request()->has('earn')){
            $paymnets =  $this->orderService->ClientPayments()->where('loyalty_points_earned' , '>' , 0)->get();

        }else{
            $paymnets =  $this->orderService->ClientPayments()->where('loyalty_points_used' , '>' , 0)->get();

        }
        return Responder::success(['points' => auth()->user()->loyalty_points , 'list' => LoyalityPointResource::collection($paymnets)]);

    }

    public function downloadInvoice($orderId)
    {
          $order = \App\Models\Order::with(['user', 'address', 'paymentMethod', 'items.product', 'providerSubOrders.provider'])
            ->findOrFail($orderId);

        // ── Site settings ──
        $siteSettings = \App\Models\SiteSetting::pluck('value', 'key');
        $logoUrl      = \App\Models\SiteSetting::where('key', 'logo')->first()
            ?->getFirstMediaUrl('logo');

        // ── Provider from sub-order ──
        $provider = $order->providerSubOrders->first()?->provider;

        $vatAmount = (float) ($order->vat_amount ?? 0);

        // ── QR code data ──
        $qrData = \App\Helpers\InvoiceQrHelper::buildInvoiceQrData(
            commercialName:      $provider?->commercial_name ?? $siteSettings['name_ar'] ?? '',
            taxNumber:           $siteSettings['tax_number'] ?? '',
            commercialRegisterNo: $provider?->commercial_register_no ?? '',
            orderNumber:         $order->order_number,
            date:                $order->created_at->format('Y-m-d H:i'),
            total:               (float) $order->total,
            vatAmount:           $vatAmount,
        );
        $qrDataUri = \App\Helpers\InvoiceQrHelper::generateBase64Png($qrData);

        // Prepare invoice data
        $invoiceData = [
            'order'          => $order,
            'customer'       => $order->user,
            'items'          => $order->items,
            'totals'         => [
                'products_total'    => (float) $order->subtotal,
                'amount'            => (float) $order->discount_amount,
                'delivery_fee'      => (float) $order->delivery_fee,
                'wallet_deduction'  => $order->wallet_deduction,
                'loyalty_deduction' => $order->loyalty_deduction,

                'final_total'       => (float) $order->total,
            ],
            'address'        => $order->address,
            // 'branch'         => $order->branch,

            'payment_method' => $order->paymentMethod,
            'logoUrl'        => $logoUrl,
            'qrDataUri'      => $qrDataUri,
            'companyName'    => $siteSettings['name_ar'] ?? $siteSettings['name_en'] ?? '',
            'taxNumber'      => $siteSettings['tax_number'] ?? '',
            'provider'       => $provider,
        ];

        // Generate PDF using mPDF for correct Arabic shaping and RTL support
        $pdf = FacadesPdf::loadView('invoices.admin_order_invoice', $invoiceData, [
            'mode' => 'utf-8',
            'format' => 'A4',
            'orientation' => 'P',
            'default_font' => 'dejavusans',
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
        ]);

        $filename = 'invoice_' .  now()->format('Ymd_His') . '.pdf';

        // Save to storage (public disk)
        \Storage::disk('public')->put('invoices/' . $filename, $pdf->output());

        // Generate the URL
        $downloadUrl = \Storage::url('invoices/' . $filename);

 // Generate the URL

        return Responder::success([
            'url' => $downloadUrl,
            'filename' => $filename,
        ], __('apis.success'));
    }

    protected function sendNewOrderNotificationToAdmins($orderNum , $id)
    {
        $message = 'يوجد طلب حجز جديد برقم #' . $orderNum;
        $admins = \App\Models\Admin::all();
        foreach ($admins as $admin) {
            $admin->notify(new \App\Notifications\NotifyAdmin([
                'title' => [
                    'ar' => 'طلب جديد',
                    'en' => 'New Order'
                ],
                'body' => [
                    'ar' => $message,
                    'en' => $message
                ],
                'type' => 'new_order',
                'order_id' => $id   
            ]));
        }
    }

    protected function sendOrderCancellationNotificationToAdmins($orderId)
    {
        $message = 'تم تقديم طلب إلغاء للطلب رقم #' . $orderId;
        $admins = \App\Models\Admin::all();
        foreach ($admins as $admin) {
            $admin->notify(new \App\Notifications\NotifyAdmin([
                'title' => [
                    'ar' => 'طلب إلغاء طلب',
                    'en' => 'Order Cancellation Request'
                ],
                'body' => [
                    'ar' => $message,
                    'en' => $message
                ],
                'type' => 'order_cancel_request',
                'order_id' => $orderId
            ]));
        }
    }


    /**
     * Calculate distance and delivery fee for address across all branches
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
// public function calculateDeliveryFee(Request $request)
// {
//     try {
//         $request->validate([
//             'address_id' => 'required|exists:addresses,id',
//         ]);

//         $user = auth()->user();
        
//         // Get the address and ensure it belongs to the authenticated user
//         $address = Address::where('id', $request->address_id)
//                          ->where('user_id', $user->id)
//                          ->firstOrFail();
        
//         // Get all active branches
//         $branches = Branch::where('status', 'active')->get();
        
//         if ($branches->isEmpty()) {
//             return Responder::error(__('apis.no_branches_available'), [], 404);
//         }
        
//         $branchResults = [];
//         $nearestBranch = null;
//         $shortestDistance = PHP_FLOAT_MAX;
        
//         // Loop through all branches to calculate distance and check polygon
//         foreach ($branches as $branch) {
//             $result = $this->orderService->calculateDistanceAndDeliveryFee($address, $branch);
            
//             $branchData = [
//                 'branch_id' => $branch->id,
//                 'branch_name' => $branch->name,
//                 'distance_km' => $result['distance_km'],
//                 'delivery_fee' => $result['delivery_fee'],
//                 'in_polygon' => $result['in_polygon'],
//             ];
            
//             $branchResults[] = $branchData;
            
//             // Track the nearest branch (prioritize polygon match, then distance)
//             if ($result['in_polygon'] || $result['distance_km'] < $shortestDistance) {
//                 if ($nearestBranch === null || 
//                     $result['in_polygon'] || 
//                     (!$nearestBranch['in_polygon'] && $result['distance_km'] < $shortestDistance)) {
//                     $nearestBranch = $branchData;
//                     $shortestDistance = $result['distance_km'];
//                 }
//             }
//         }
        
//         // Sort results by polygon match first, then by distance
//         usort($branchResults, function($a, $b) {
//             if ($a['in_polygon'] && !$b['in_polygon']) return -1;
//             if (!$a['in_polygon'] && $b['in_polygon']) return 1;
//             return $a['distance_km'] <=> $b['distance_km'];
//         });
        
//         return Responder::success(
//              $nearestBranch,
         
//         );
        
//     } catch (\Illuminate\Validation\ValidationException $e) {
//         return Responder::error(__('apis.validation_error'), $e->errors(), 422);
//     } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
//         return Responder::error(__('apis.not_found'), [], 404);
//     } catch (\Exception $e) {
//         return Responder::error($e->getMessage(), [], 500);
//     }
// }



    protected function notifyAdminsOfProblem(Order $order)
{
    $problem = Problem::find($order->problem_id);
    $admins = \App\Models\Admin::all();
    foreach ($admins as $admin) {
        $admin->notify(new OrderProblemReportedNotification([
            'title' => [
                'ar' => 'تم التبليغ عن مشكلة في الطلب',
                'en' => 'Problem Reported in Order'
            ],
            'body' => [
'ar' => "تم التبليغ عن مشكلة: " . ($problem?->problem ?? $problem?->notes ?? 'غير محددة') . " في الطلب رقم #{$order->order_number}",
'en' => "A problem was reported: " . ($problem?->problem ?? $problem?->notes ?? 'Unknown') . " in order #{$order->order_number}",
            ],
            'type' => 'order_problem_reported',
            'order_id' => $order->id
        ]));
    }
}


}
