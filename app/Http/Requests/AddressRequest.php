<?php

namespace App\Http\Requests;

use App\Enums\AddressType;
use App\Models\District;
use App\Services\CityBoundaryService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class AddressRequest extends FormRequest
{
    public function authorize()
    {
        return true;  
    }

    public function rules()
    {
        $isCreate = $this->isMethod('post');
        $requiredRules = $isCreate ? ['required'] : ['sometimes', 'required'];
        $requiredStr = implode('|', $requiredRules);

        return [
            'phone' => 'nullable|phone:SA',
            'description' => 'nullable|string|max:255',
            'address_name' => array_merge($requiredRules, [
                'min:3',
                'regex:/[\p{L}]/u', // must contain at least one letter
                'regex:/^[\p{L}\p{N} ]+$/u', // only letters, numbers, spaces
            ]),
            'city_id' => 'nullable|exists:cities,id',
            'districts_id' => 'nullable|exists:districts,id',
            'district_name' => 'nullable|string|max:255',
            'country_code' => 'nullable|numeric|digits_between:2,5',
            'latitude' => $requiredStr . '|numeric|between:-90,90',
            'longitude' => $requiredStr . '|numeric|between:-180,180',
            'is_default' => 'nullable|boolean',
            'building_number' => 'nullable|string|max:50',
            'floor_number' => 'nullable|string|max:50',
           'note' => 'nullable|string|max:50',

        'address_type' => ['nullable', new Enum(AddressType::class)],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (!$this->filled('district_name') && $this->filled('districts_id')) {
            $districtName = District::query()->where('id', $this->input('districts_id'))->value('name');
            $this->merge(['district_name' => $districtName]);
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $messageKey = app(CityBoundaryService::class)->boundaryError(
                $this->input('city_id'),
                $this->input('latitude'),
                $this->input('longitude')
            );

            if ($messageKey) {
                $validator->errors()->add('city_id', __($messageKey));
            }
        });
    }
}
