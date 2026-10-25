<?php

namespace App\Http\Controllers\Admin;

use App\Models\Order;
use App\Models\Coupon;
use App\Models\User;
use App\Traits\Report;
use App\Models\Provider;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\coupons\Store;
use App\Http\Requests\Admin\coupons\Update;
use App\Http\Requests\Admin\Coupon\renewCouponRequest;


class CouponController extends Controller
{
    public function index($id = null)
    {
        if (request()->ajax()) {
            $coupons = Coupon::search(request()->searchArray)->withCount('orders')->paginate(30);
            $html = view('admin.coupons.table' ,compact('coupons'))->render() ;
            return response()->json(['html' => $html]);
        }
        return view('admin.coupons.index');
    }

    public function create()
    {
        $providers = Provider::where('status', 'accepted')->get();
        $clients = User::query()
            ->where('type', 'client')
            ->where('is_active', 1)
            ->orderBy('name')
            ->get(['id', 'name', 'phone']);

        return view('admin.coupons.create', compact('providers', 'clients'));
    }


    public function store(Store $request)
    {
        $coupon = Coupon::create(
            $request->except(['expire_date', 'client_ids'])
            + (['expire_date' => $request->expire_date ? date('Y-m-d H:i:s', strtotime($request->expire_date)) : null])
        );

        if ($request->audience_type === 'specific') {
            $coupon->users()->sync($request->client_ids ?? []);
        } else {
            $this->attachAllActiveClientsToCoupon($coupon);
        }

        Report::addToLog('  اضافه كوبون خصم') ;

        // Notify target clients (all or specific) about the new coupon
        try {
            $data = [
                'coupon_name'   => $coupon->coupon_name,
                'coupon_code'   => $coupon->coupon_num,
                'start_date'    => optional($coupon->start_date)->format('Y-m-d'),
                'end_date'      => optional($coupon->expire_date)->format('Y-m-d'),
                'discount'      => $coupon->discount,
                'discount_type' => $coupon->type,
                'usage_time'    => $coupon->usage_time,
            ];

            $this->notifyCouponAudience($coupon, $data);
        } catch (\Throwable $e) {
            \Log::error('Failed to send coupon created notifications', [
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json(['url' => route('admin.coupons.index')]);
    }

    public function edit($id)
    {
        $coupon = Coupon::with('users:id')->findOrFail($id);
        $providers = Provider::where('status', 'accepted')->get();
        $clients = User::query()
            ->where('type', 'client')
            ->where('is_active', 1)
            ->orderBy('name')
            ->get(['id', 'name', 'phone']);

        return view('admin.coupons.edit', compact('coupon', 'providers', 'clients'));
    }

    public function update(Update $request, $id)
    {
        $coupon = Coupon::findOrFail($id);
        $coupon->update(
            $request->except(['expire_date', 'client_ids'])
            + (['expire_date' => $request->expire_date ? date('Y-m-d H:i:s', strtotime($request->expire_date)) : null])
        );

        if ($request->audience_type === 'specific') {
            $coupon->users()->sync($request->client_ids ?? []);
        } else {
            $this->attachAllActiveClientsToCoupon($coupon);
        }

        try {
            $data = [
                'coupon_name'   => $coupon->coupon_name,
                'coupon_code'   => $coupon->coupon_num,
                'start_date'    => optional($coupon->start_date)->format('Y-m-d'),
                'end_date'      => optional($coupon->expire_date)->format('Y-m-d'),
                'discount'      => $coupon->discount,
                'discount_type' => $coupon->type,
                'usage_time'    => $coupon->usage_time,
            ];

            $this->notifyCouponAudience($coupon, $data);
        } catch (\Throwable $e) {
            \Log::error('Failed to send coupon update notifications', [
                'error' => $e->getMessage(),
                'coupon_id' => $coupon->id,
            ]);
        }

        Report::addToLog('  تعديل كوبون خصم') ;
        return response()->json(['url' => route('admin.coupons.index')]);
    }
    public function show($id)
    {
        $coupon = Coupon::findOrFail($id);
        return view('admin.coupons.show' , ['coupon' => $coupon]);
    }
    public function destroy($id)
    {
        $coupon = Coupon::findOrFail($id)->delete();
        Report::addToLog('  حذف كوبون خصم') ;
        return response()->json(['id' =>$id]);
    }

    public function destroyAll(Request $request)
    {
        $requestIds = json_decode($request->data);

        foreach ($requestIds as $id) {
            $ids[] = $id->id;
        }
        if (Coupon::whereIntegerInRaw('id',$ids)->get()->each->delete()) {
            Report::addToLog('  حذف العديد من كوبونات الخصم') ;
            return response()->json('success');
        } else {
            return response()->json('failed');
        }
    }

    public function renew(renewCouponRequest $request)
    {
        $coupon = Coupon::findOrFail($request->id);

        if ($request->status == 'closed') {
            $coupon->update(['is_active' => false]);
            $html = '<span class="btn btn-sm round btn-outline-success open-coupon" data-toggle="modal" id="div_'.$coupon->id.'" data-target="#notify" data-id="'.$coupon->id.'">
                        '.__('admin.reactivation_Coupon').'  <i class="feather icon-rotate-cw"></i>
                    </span>';
        } else {
            $updateData = $request->except(['expire_date']);
            if ($request->expire_date) {
                $updateData['expire_date'] = date('Y-m-d H:i:s', strtotime($request->expire_date));
            }
            $updateData['is_active'] = true;

            $coupon->update($updateData);
            $html = '<span class="btn btn-sm round btn-outline-danger change-coupon-status" data-status="closed" data-id="'.$coupon->id.'">
                        '.__('admin.Stop_Coupon').'  <i class="feather icon-slash"></i>
                    </span>';
        }

        return response()->json([
            'message' => __('admin.update_coupon_status_successfully'),
            'html' => $html,
            'id' => $request->id
        ]);
    }

    public function orders(Request $request, $couponId)
    {
        $orders = Order::with(['user', 'provider'])
            ->where('coupon_id', $couponId)
            ->search($request->searchArray ?? [])
            ->paginate(30);
    
        if ($request->ajax()) {
            $html = view('admin.coupons.orders.table', compact('orders'))->render();
            return response()->json(['html' => $html]);
        }
    
        return view('admin.coupons.orders.index', compact('couponId'));
    }

    private function notifyCouponAudience(Coupon $coupon, array $data): void
    {
        $query = User::query()
            ->where('type', 'client')
            ->where('is_notify', true);

        if (($coupon->audience_type ?? 'all') === 'specific') {
            $userIds = $coupon->users()->pluck('users.id')->toArray();
            if (empty($userIds)) {
                return;
            }
            $query->whereIn('id', $userIds);
        }

        $query->chunk(200, function ($users) use ($data) {
            app(\App\Services\NotificationService::class)->send($users, 'coupon_created', $data);
        });
    }

    private function attachAllActiveClientsToCoupon(Coupon $coupon): void
    {
        $clientIds = User::query()
            ->where('type', 'client')
            ->where('is_active', 1)
            ->pluck('id');

        if ($clientIds->isEmpty()) {
            return;
        }

        $pivotData = [];
        foreach ($clientIds as $id) {
            $pivotData[$id] = ['used_times' => 0];
        }

        // Add all active clients without resetting existing usage counters.
        $coupon->users()->syncWithoutDetaching($pivotData);
    }
    
}
