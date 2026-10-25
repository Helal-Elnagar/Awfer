<?php

namespace App\Http\Requests\Admin\products;

use Illuminate\Foundation\Http\FormRequest;

class Store extends FormRequest
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
            'name.*'                => 'required|string|min:1|max:191',
            'description'           => 'required|array',
            'description.*'         => 'required|string',
            'parent_category_id'    => 'required|exists:categories,id',
            'brand_id'              => 'required|exists:brands,id',
            'base_price'            => 'required|numeric|min:1',
            'final_price'           => 'required|numeric|min:1|lte:base_price',
            'quantity'              => 'required|integer|min:0',
            'images'                => 'nullable|array|max:10',
            'images.*'              => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:4096',
            'is_active'             => 'nullable|boolean',
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
        return [
            'final_price.required' => __('admin.final_price_required'),
            'final_price.numeric' => __('admin.final_price_numeric'),
            'final_price.min' => __('admin.final_price_min'),
            'final_price.lte' => __('admin.final_price_exceeds_base'),
            'options.required' => __('admin.options_required'),
            'options.*.name.required' => __('admin.option_name_required'),
            'options.*.name.string' => __('admin.option_name_string'),
            'options.*.name.max' => __('admin.option_name_max'),
            'options.*.type.required' => __('admin.option_type_required'),
            'options.*.type.in' => __('admin.option_type_in'),
            'options.*.additional_price.numeric' => __('admin.option_additional_price_numeric'),
            'options.*.additional_price.min' => __('admin.option_additional_price_min'),
            'options.*.is_default.boolean' => __('admin.option_is_default_boolean'),
        ];
    }
}
