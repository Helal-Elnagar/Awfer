<?php

namespace App\Http\Requests\Admin\products;

use Illuminate\Foundation\Http\FormRequest;

class Update extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $default_lang = config('app.locale');

        return [
            'name'                  => 'required|array',
            'name.*'                => 'nullable|string|max:191',
            'description'           => 'nullable|array',
            'description.*'         => 'nullable|string',
            'parent_category_id'    => 'required|exists:categories,id',
            'base_price'            => 'required|numeric|min:0',
            'final_price'           => 'required|numeric|min:0|lte:base_price',
            'quantity'              => 'required|integer|min:0',
            'images'                => 'nullable|array|max:10',
            'images.*'              => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:4096',
            'is_active'             => 'nullable|boolean',
            'brand_id'              => 'required|exists:brands,id',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $basePrice = $this->input('base_price');
            $finalPrice = $this->input('final_price');

            if (is_numeric($basePrice) && is_numeric($finalPrice) && $finalPrice > $basePrice) {
                $validator->errors()->add('final_price', __('admin.final_price_exceeds_base'));
            }
        });
    }

    public function messages()
    {
        $ar = app()->getLocale() == 'ar';
        return [
            'final_price.required' => $ar ? 'سعر البيع النهائي مطلوب.' : 'The final price is required.',
            'final_price.numeric' => $ar ? 'سعر البيع النهائي يجب أن يكون رقم.' : 'The final price must be a number.',
            'final_price.min' => $ar ? 'سعر البيع النهائي يجب أن يكون على الأقل 0.' : 'The final price must be at least 0.',
            'final_price.lte' => $ar ? 'سعر البيع النهائي لا يمكن أن يكون أكبر من السعر الأساسي.' : 'The final price cannot exceed the base price.',
            'options.*.name.required' => $ar ? 'هذا الحقل مطلوب.' : 'The option name is required.',
            'options.*.name.string' => $ar ? 'يجب أن يكون اسم الخيار نصاً.' : 'The option name must be a string.',
            'options.*.name.max' => $ar ? 'اسم الخيار يجب ألا يزيد عن 191 حرفاً.' : 'The option name may not be greater than 191 characters.',
            'options.*.type.required' => $ar ? 'نوع الخيار مطلوب.' : 'The option type is required.',
            'options.*.type.in' => $ar ? 'نوع الخيار يجب أن يكون: وزن، تقطيع، تغليف.' : 'The option type must be one of: weight, cutting, packaging.',
            'options.*.additional_price.numeric' => $ar ? 'سعر الإضافة يجب أن يكون رقم.' : 'The additional price must be a number.',
            'options.*.additional_price.min' => $ar ? 'سعر الإضافة يجب أن يكون على الأقل 0.' : 'The additional price must be at least 0.',
            'options.*.is_default.boolean' => $ar ? 'قيمة الخيار الافتراضي يجب أن تكون نعم أو لا.' : 'The is default field must be true or false.',
        ];
    }
}
