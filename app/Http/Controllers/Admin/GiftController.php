<?php

namespace App\Http\Controllers\Admin;

use App\Models\Gift;
use App\Models\LevelGift;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Traits\Report;



class GiftController extends Controller
{
public function index()
    {
        if (request()->ajax()) {
            $gifts = Gift::with('level')->search(request()->searchArray)->paginate(30);
            $html = view('admin.gifts.table', ['gifts' => $gifts])->render();
            return response()->json(['html' => $html]);
        }

        $gifts = Gift::with('level')->paginate(30);
        return view('admin.gifts.index', compact('gifts'));
    }

    public function create()
    {
        $levels = LevelGift::all();
        return view('admin.gifts.create', compact('levels'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:191',
            'level_id' => 'required|exists:levels_gift,id',
        ]);

        $data = $request->only(['name', 'level_id']);
        Gift::create($data);

return redirect()->route('admin.gifts.index')
    ->with('success', __('admin.gift_added_successfully'));    }

    public function edit($id)
    {
        $gift = Gift::findOrFail($id);
        $levels = LevelGift::all();
        return view('admin.gifts.edit', compact('gift', 'levels'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:191',
            'level_id' => 'required|exists:levels_gift,id',
        ]);

        $gift = Gift::findOrFail($id);
        $data = $request->only(['name', 'level_id']);
        $gift->update($data);

return redirect()->route('admin.gifts.index')
    ->with('success', __('admin.gift_updated_successfully'));
    }

    public function show($id)
    {
        $gift = Gift::with('level')->findOrFail($id);
        return view('admin.gifts.show', compact('gift'));
    }

    public function destroy($id)
    {
        Gift::findOrFail($id)->delete();
        return response()->json(['id' => $id]);
    }
      public function destroyAll(Request $request)
    {
        $requestIds = json_decode($request->data);
        
        foreach ($requestIds as $id) {
            $ids[] = $id->id;
        }
        if (Gift::whereIntegerInRaw('id',$ids)->get()->each->delete()) {
            Report::addToLog('  حذف العديد من الهدايا') ;
            return response()->json('success');
        } else {
            return response()->json('failed');
        }
    }
}
