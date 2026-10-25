<?php

namespace App\Http\Controllers\Admin;

use App\Models\Brand;
use App\Traits\Report;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\products\Store;
use App\Http\Requests\Admin\products\Update;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{
    public function index($id = null)
    {
        if (request()->ajax()) {
            $products = Product::with(['category', 'options'])
                ->search(request()->searchArray)
                ->paginate(30);

            $html = view('admin.products.table', compact('products'))->render();
            return response()->json(['html' => $html]);
        }
        return view('admin.products.index');
    }

    public function create()
    {
        $categories = Category::where('is_active', 1)->get();
        $brands  = Brand::where('is_active', 1)->get();
        return view('admin.products.create', compact('categories', 'brands'));
    }

    public function store(Store $request)
    {
        $data = $request->validated();
        $data['discount_percentage'] = $this->calculateDiscount($data['base_price'], $data['final_price']);
        unset($data['final_price']);
        $data['category_id'] = $request->parent_category_id;
        $product = Product::create($data);

        try {
            if ($request->hasFile('images')) {
                $files = $request->file('images');
                if (count($files) > 10) {
                    return response()->json([
                        'error' => true,
                        'message' => 'لا يمكن رفع أكثر من 10 صور للمنتج'
                    ], 422);
                }
                foreach ($files as $file) {
                    if ($file && $file->isValid()) {
                        $product->addMedia($file)->toMediaCollection('product-images');
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('فشل رفع صور المنتج عند الإنشاء', ['product_id' => $product->id, 'error' => $e->getMessage()]);
            return response()->json([
                'error' => true,
                'message' => 'حدث خطأ أثناء رفع الصور'
            ], 500);
        }

        Report::addToLog('اضافه منتج');
        return response()->json(['url' => route('admin.products.index')]);
    }

    private function calculateDiscount(float $basePrice, float $finalPrice): float
    {
        if ($basePrice <= 0) {
            return 0;
        }

        return round(($basePrice - $finalPrice) / $basePrice * 100, 2);
    }

    public function edit($id)
    {
        $product = Product::with('options')->findOrFail($id);

        $categories = Category::all();
        $brands = Brand::all();

        return view('admin.products.edit', compact('product', 'categories', 'brands'));
    }

    public function update(Update $request, $id)
    {
        $product = Product::findOrFail($id);

        $data = $request->validated();
        $data['discount_percentage'] = $this->calculateDiscount($data['base_price'], $data['final_price']);
        unset($data['final_price']);
        $data['category_id'] = $request->parent_category_id;
        $product->update($data);

        // حذف الصور القديمة فقط إذا تم رفع صور جديدة
        if ($request->hasFile('images')) {
            $product->clearMediaCollection('product-images'); // حذف الصور القديمة
            foreach ($request->file('images') as $image) {
                $product->addMedia($image)->toMediaCollection('product-images');
            }
        }

        return response()->json([
            'url' => route('admin.products.index'),
            'success' => true
        ]);
    }


    public function show($id)
    {
        $product = Product::with(['category', 'options'])->findOrFail($id);
        return view('admin.products.show', compact('product'));
    }

    public function destroy($id)
    {
        $product = Product::findOrFail($id);

        if ($product->orderItems()->exists()) {
            return response()->json([
                'error' => true,
                'message' => 'لا يمكن حذف هذا المنتج لأنه مرتبط بطلبات موجودة'
            ], 422);
        }

        $product->delete();
        Report::addToLog('حذف منتج');
        return response()->json(['id' => $id]);
    }

    public function deleteAll(Request $request)
    {
        $requestIds = json_decode($request->data);
        $ids = [];

        foreach ($requestIds as $id) {
            $ids[] = $id->id;
        }

        $productsWithOrders = Product::whereIntegerInRaw('id', $ids)
            ->whereHas('orderItems')
            ->pluck('name')
            ->toArray();

        if (!empty($productsWithOrders)) {
            $productNames = implode('، ', $productsWithOrders);
            return response()->json([
                'error' => true,
                'message' => 'لا يمكن حذف المنتجات التالية لأنها مرتبطة بطلبات موجودة: ' . $productNames
            ], 422);
        }

        if (Product::whereIntegerInRaw('id', $ids)->get()->each->delete()) {
            Report::addToLog('حذف العديد من منتجات');
            return response()->json('success');
        } else {
            return response()->json('failed');
        }
    }

    public function toggleStatus(Request $request)
    {
        $product = Product::findOrFail($request->id);
        $product->update(['is_active' => !$product->is_active]);

        $status = $product->is_active ? 'تفعيل' : 'إلغاء تفعيل';
        Report::addToLog($status . ' منتج');

        return response()->json([
            'status' => $product->is_active,
            'message' => 'تم تحديث حالة المنتج بنجاح'
        ]);
    }

    public function deleteImage($mediaId)
    {
        try {
            if (!auth()->guard('admin')->check()) {
                return response()->json(['success' => false, 'message' => 'غير مصرح'], 403);
            }
            $media = \Spatie\MediaLibrary\MediaCollections\Models\Media::find($mediaId);
            if (!$media) {
                return response()->json(['success' => false, 'message' => 'الصورة غير موجودة']);
            }

            // حذف كامل من قاعدة البيانات ونظام الملفات عبر Spatie MediaLibrary
            $media->delete();

            Report::addToLog('حذف صورة منتج: media_id=' . $mediaId);

            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            Log::error('خطأ أثناء حذف صورة المنتج', ['media_id' => $mediaId, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'تعذر حذف الصورة'], 500);
        }
    }
}
