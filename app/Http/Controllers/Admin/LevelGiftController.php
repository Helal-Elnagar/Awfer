<?php

namespace App\Http\Controllers\Admin;

use App\Models\LevelGift;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class LevelGiftController extends Controller
{
public function index()
{
    $levels = LevelGift::all();
    return view('admin.level_gifts.index', compact('levels'));
}

public function update(Request $request)
{
    $levels = $request->input('levels');

    $previousPoints = -1;
    foreach ($levels as $id => $data) {
        $points = intval($data['points_required']);
        if ($points <= $previousPoints) {
            return redirect()->back()
                ->withInput()
                ->withErrors([
            'levels' => "يجب أن تكون النقاط لكل مستوى أعلى من المستوى السابق. المستوى رقم $id يحتوي على نقاط غير صحيحة."
                ]);
        }
        $previousPoints = $points;
    }

    // التحقق من بقية الحقول
    $request->validate([
        'levels.*.percentage' => 'nullable|numeric|min:0|max:100',
        'levels.*.name' => 'required|string|max:191',
    ]);

    // تحديث المستويات
    foreach ($levels as $id => $data) {
        $level = LevelGift::find($id);
        $level->update($data);
    }

    return redirect()->back()->with('success', 'تم تحديث المستويات بنجاح');
}


}
