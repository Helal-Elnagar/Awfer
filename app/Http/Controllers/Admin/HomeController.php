<?php

namespace App\Http\Controllers\Admin;

use Carbon\Carbon;
use App\Models\City;
use App\Models\Order;
use App\Models\User;
use App\Traits\Menu;
use App\Models\Country;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Admin\UpdateProfile;
use App\Http\Requests\Admin\Auth\updatePassword;
use Hash ;
class HomeController extends Controller
{
    use Menu ;
    /***************** dashboard *****************/
    public function dashboard()
    {
        $countryArray   = $this->chartData(new Country);
        $cityArray      = $this->chartData(new City);
        $menus          = $this->home() ;
        $colores        = ['info' , 'danger' , 'warning' , 'success' , 'primary'];
        $admin          = auth('admin')->user();

        $ordersQuery = Order::query()
            ->with(['user:id,name,phone', 'address:id,address_name,latitude,longitude'])
            ->select([
                'id',
                'order_number',
                'user_id',
                'address_id',
                'status',
                'total',
                'total',
                'created_at',
            ])
            ->where(function ($query) {
                $query
                    ->whereHas('address', function ($addressQuery) {
                        $addressQuery
                            ->whereNotNull('latitude')
                            ->whereNotNull('longitude');
                    });
            });

        // Keep dashboard visibility consistent with the orders screen.
        if ($admin && (int) $admin->role_id === 2) {
            $branchIds = \App\Models\BranchManager::where('manager_id', $admin->id)->pluck('branch_id');
            if ($branchIds->isNotEmpty()) {
                $ordersQuery->whereIn('branch_id', $branchIds);
            } else {
                $ordersQuery->whereRaw('1 = 0');
            }
        }

        $orderLocations = $ordersQuery
            ->latest('id')
            ->limit(300)
            ->get()
            ->map(function ($order) {
                $latitude = $order->address?->latitude;
                $longitude = $order->address?->longitude;

                if (is_null($latitude) || is_null($longitude)) {
                    return null;
                }

                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'customer_name' => $order->user?->name,
                    'customer_phone' => $order->user?->phone,
                    'status' => $order->status,
                    'total' => (float) ($order->final_total ?? $order->total ?? 0),
                    'address_name' => $order->address?->address_name,
                    'latitude' => (float) $latitude,
                    'longitude' => (float) $longitude,
                    'show_url' => route('admin.orders.show', ['id' => $order->id]),
                    'created_at' => optional($order->created_at)->format('Y-m-d H:i'),
                ];
            })
            ->filter()
            // Group orders at same location into one marker
            ->groupBy(fn($o) => $o['latitude'] . ',' . $o['longitude'])
            ->map(fn($group) => $group->values()->all())
            ->values();
        
        return view('admin.dashboard.index' , compact('menus' ,'colores'  ,'countryArray' , 'cityArray', 'orderLocations' ));
    }

    public function profile() {
        return view('admin.admins.profile');
    }

    
    public function updateProfile(UpdateProfile $request) {
        auth('admin')->user()->update($request->validated());
        return back()->with('success' , __('admin.update_successfullay'));
    }
    
    public function updatePassword(updatePassword $request) {
        if (!Hash::check($request->old_password , auth('admin')->user()->password)) {
            return back()->with('danger' , __('admin.not_old_password'));
        }
        auth('admin')->user()->update(['password' => $request->password]);
        auth('admin')->logout();
        return redirect()->route('admin.show.login')->with('success' , __('admin.update_successfullay'));
    }






    public function chartData($model)
    {
        $users = $model::select('id', 'created_at')
        ->get()
        ->groupBy(function($date) {
            return Carbon::parse($date->created_at)->format('Y-m'); 
        });
        $usermcount = [];
        $userArr = [];

        foreach ($users as $key => $value) {
            $usermcount[$key] = count($value);
        }
        for($i = 1; $i <= 12; $i++){
            $d = ($i < 10 )? date('Y').'-0'.$i : date('Y').'-'.$i;
            if(!empty($usermcount[$d])){
                $userArr[] = $usermcount[$d];
            }else{
                $userArr[] = 0;
            }
        }
        return $userArr ; 

    }
}
