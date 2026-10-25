<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\clientreviews\Store;
use App\Http\Requests\Admin\clientreviews\Update;
use App\Models\ClientReview ;
use App\Traits\Report;


class ClientReviewController extends Controller
{
    public function index($id = null)
    {
        if (request()->ajax()) {
            $clientreviews = ClientReview::search(request()->searchArray)->paginate(30);
            $html = view('admin.clientreviews.table' ,compact('clientreviews'))->render() ;
            return response()->json(['html' => $html]);
        }
        return view('admin.clientreviews.index');
    }

    public function create()
    {
        return view('admin.clientreviews.create');
    }


    public function store(Store $request)
    {
        ClientReview::create($request->validated());
        Report::addToLog('  اضافه رأيالعميل') ;
        return response()->json(['url' => route('admin.clientreviews.index')]);
    }
    public function edit($id)
    {
        $clientreview = ClientReview::findOrFail($id);
        return view('admin.clientreviews.edit' , ['clientreview' => $clientreview]);
    }

    public function update(Update $request, $id)
    {
        $clientreview = ClientReview::findOrFail($id)->update($request->validated());
        Report::addToLog('  تعديل رأيالعميل') ;
        return response()->json(['url' => route('admin.clientreviews.index')]);
    }

    public function show($id)
    {
        $clientreview = ClientReview::findOrFail($id);
        return view('admin.clientreviews.show' , ['clientreview' => $clientreview]);
    }
    public function destroy($id)
    {
        $clientreview = ClientReview::findOrFail($id)->delete();
        Report::addToLog('  حذف رأيالعميل') ;
        return response()->json(['id' =>$id]);
    }

    public function destroyAll(Request $request)
    {
        $requestIds = json_decode($request->data);
        
        foreach ($requestIds as $id) {
            $ids[] = $id->id;
        }
        if (ClientReview::whereIntegerInRaw('id',$ids)->get()->each->delete()) {
            Report::addToLog('  حذف العديد من اراءالعملاء') ;
            return response()->json('success');
        } else {
            return response()->json('failed');
        }
    }
}
