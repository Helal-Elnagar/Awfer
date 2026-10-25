<?php

namespace App\Http\Controllers\Website;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Repositories\AddressRepository;
use App\Models\City;
use App\Models\District;
use App\Models\Address;
use App\Services\CityBoundaryService;

class AddressController extends Controller
{
    protected AddressRepository $addresses;

    public function __construct(AddressRepository $addresses)
    {
        $this->addresses = $addresses;
    }

    /**
     * Display a listing of the user's addresses.
     */
    public function index()
    {
        $user = Auth::guard('web')->user();
        $addresses = $this->addresses->getUserAddresses($user);
        return view('website.pages.account.addresses', compact('addresses'));
    }

    /**
     * Show the form for creating a new address.
     */
    public function create()
    {
        $cities = City::select('id', 'name')->orderBy('name')->get();
        return view('website.pages.account.create_address', compact('cities'));
    }

    /**
     * Store a newly created address in storage.
     */
    public function store(Request $request)
    {
        $user = Auth::guard('web')->user();

        $validated = $request->validate([
            'address_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'city' => ['required', 'integer', 'exists:cities,id'],
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'country_code' => 'nullable',
            'district' => ['required', 'string', 'max:255'],
        ]);

        $boundaryError = app(CityBoundaryService::class)->boundaryError(
            $validated['city'],
            $validated['latitude'],
            $validated['longitude']
        );
        if ($boundaryError) {
            return back()->withErrors(['city' => __($boundaryError)])->withInput();
        }

        $districtId = is_numeric($validated['district']) ? (int) $validated['district'] : null;
        $districtName = $this->resolveDistrictName($validated['district']);

        $data = [
            'user_id' => $user->id,
            'address_name' => $validated['address_name'],
            'phone' => $validated['phone'] ?? null,
            'city_id' => $validated['city'],
            'districts_id' => $districtId,
            'district_name' => $districtName,
            'description' => $validated['address_desc'] ?? null,
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'country_code' => $validated['country_code'] ?? null,
        ];

        $this->addresses->create($data);

        return redirect()->route('website.addresses.index')
            ->with('success', __('تم إضافة العنوان بنجاح'));
    }

    /**
     * Show the form for editing the specified address.
     */
    public function edit(Address $address)
    {
        $user = Auth::guard('web')->user();
        if (!$this->addresses->belongsToUser($address, $user)) {
            abort(403);
        }

        $cities = City::select('id', 'name')->orderBy('name')->get();
        $districts = District::select('id', 'name')
            ->where('city_id', $address->city_id)
            ->orderBy('name')
            ->get();

        return view('website.pages.account.edit_address', compact('address', 'cities', 'districts'));
    }

    /**
     * Update the specified address in storage.
     */
    public function update(Request $request, Address $address)
    {
        $user = Auth::guard('web')->user();
        if (!$this->addresses->belongsToUser($address, $user)) {
            abort(403);
        }

        $validated = $request->validate([
            'address_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'city' => ['required', 'integer', 'exists:cities,id'],
            'latitude'=> ['required', 'numeric', 'between:-90,90'],
            'longitude'=> ['required', 'numeric', 'between:-180,180'],
            'country_code' => 'nullable',
            'district' => ['required', 'string', 'max:255'],
            'address_desc' => ['nullable', 'string', 'max:500'],
            'address_map' => ['nullable', 'string'],
        ]);

        $boundaryError = app(CityBoundaryService::class)->boundaryError(
            $validated['city'],
            $validated['latitude'],
            $validated['longitude']
        );
        if ($boundaryError) {
            return back()->withErrors(['city' => __($boundaryError)])->withInput();
        }

        $districtId = is_numeric($validated['district']) ? (int) $validated['district'] : null;
        $districtName = $this->resolveDistrictName($validated['district']);

        $data = [
            'address_name' => $validated['address_name'],
            'phone' => $validated['phone'] ?? null,
            'city_id' => $validated['city'],
            'districts_id' => $districtId,
            'district_name' => $districtName,
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'country_code' => $validated['country_code'] ?? null,
            'description' => $validated['address_desc'] ?? null,
        ];

        $this->addresses->update($address, $data);

        return redirect()->route('website.addresses.index')
            ->with('success', __('تم تحديث العنوان بنجاح'));
    }

    /**
     * Remove the specified address from storage.
     */
    public function destroy(Address $address)
    {
        $user = Auth::guard('web')->user();
        if (!$this->addresses->belongsToUser($address, $user)) {
            abort(403);
        }

        // Check if address is related to any orders
        if ($address->hasOrders()) {
            return redirect()->route('website.addresses.index')
                ->with('error', 'لا يمكن حذف هذا العنوان لأنه مرتبط بطلبات سابقة');
        }

        $this->addresses->delete($address);

        return redirect()->route('website.addresses.index')
            ->with('success', __('تم حذف العنوان بنجاح'));
    }

    private function resolveDistrictName(string $district): ?string
    {
        if (!is_numeric($district)) {
            return $district;
        }

        return District::query()->where('id', (int) $district)->value('name');
    }
}
