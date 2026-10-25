<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\DeliveryPeriod;
use App\Traits\Report;

class DeliveryPeriodController extends Controller
{
    public function index()
    {
        $maxCount = config('awt.delivery_periods_max_count', 6);
        $maxReached = DeliveryPeriod::count() >= $maxCount;

        if (request()->ajax()) {
            $periods = DeliveryPeriod::search(request()->searchArray)->paginate(30);
            $html = view('admin.delivery_periods.table', compact('periods', 'maxReached'))->render();
            return response()->json(['html' => $html, 'maxReached' => $maxReached]);
        }
        return view('admin.delivery_periods.index', compact('maxReached'));
    }

    public function create()
    {
        return view('admin.delivery_periods.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        $maxCount = config('awt.delivery_periods_max_count', 6);
        if (DeliveryPeriod::count() >= $maxCount) {
            return response()->json([
                'message' => __('admin.you_cannot_add_more_delivery_periods', ['count' => $maxCount]),
            ], 422);
        }

        DeliveryPeriod::create([
            'name'        => $request->name,
            'description' => $request->description,
        ]);
        Report::addToLog('إضافة فترة توصيل');
        return response()->json(['url' => route('admin.delivery_periods.index')]);
    }

    public function edit($id)
    {
        $period = DeliveryPeriod::findOrFail($id);
        return view('admin.delivery_periods.edit', compact('period'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        DeliveryPeriod::findOrFail($id)->update([
            'name'        => $request->name,
            'description' => $request->description,
        ]);
        Report::addToLog('تعديل فترة توصيل');
        return response()->json(['url' => route('admin.delivery_periods.index')]);
    }

    public function destroy($id)
    {
        DeliveryPeriod::findOrFail($id)->delete();
        Report::addToLog('حذف فترة توصيل');
        return response()->json(['id' => $id]);
    }

    public function destroyAll(Request $request)
    {
        $requestIds = json_decode($request->data);
        $ids = [];
        foreach ($requestIds as $item) {
            $ids[] = $item->id;
        }
        DeliveryPeriod::whereIntegerInRaw('id', $ids)->delete();
        Report::addToLog('حذف مجموعة فترات توصيل');
        return response()->json('success');
    }
}
