<?php

namespace App\Http\Requests\Website\Auth;

use App\Models\District;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        // Extract country code and phone from intlTelInput format
        if ($this->has('phone')) {
            $phone = $this->input('phone');

            // Remove any non-numeric characters
            $phone = preg_replace('/[^0-9]/', '', $phone);
            
            // Default to Saudi Arabia country code
            $countryCode = '966';
            
            // If phone starts with country code, extract it
            if (strlen($phone) > 10) {
                if (str_starts_with($phone, '966')) {
                    $countryCode = '966';
                    $phone = substr($phone, 3);
                } elseif (str_starts_with($phone, '965')) {
                    $countryCode = '965';
                    $phone = substr($phone, 3);
                } elseif (str_starts_with($phone, '971')) {
                    $countryCode = '971';
                    $phone = substr($phone, 3);
                }
            }
            
            // Remove leading zero if present
            $phone = ltrim($phone, '0');
            
            $this->merge([
                'phone' => $phone,
                'country_code' => $countryCode,
                'type' => 'client', // Default user type for website registration
            ]);
        }

        if (!$this->filled('district_name') && $this->filled('district_id')) {
            $districtName = District::query()->where('id', $this->input('district_id'))->value('name');
            $this->merge(['district_name' => $districtName]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'name' => 'required|string|min:3|max:60',
            'phone' => 'required|numeric|digits_between:8,10|unique:users,phone,NULL,id,deleted_at,NULL',
            'country_code' => 'required|string|max:10',
            'email' => 'nullable|email|max:191|unique:users,email,NULL,id,deleted_at,NULL',
            'gender' => 'required|in:male,female',
            'city_id' => 'required|exists:cities,id',
            'district_name' => 'required|string|max:255',
            'password' => 'required|string|min:6|max:100|confirmed',
            'password_confirmation' => 'required|string|min:6|max:100',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'name.required' => 'الاسم مطلوب',
            'name.min' => 'الاسم يجب أن يكون 3 أحرف على الأقل',
            'name.max' => 'الاسم يجب ألا يتجاوز 60 حرف',
            'phone.required' => 'رقم الجوال مطلوب',
            'phone.numeric' => 'رقم الجوال يجب أن يحتوي على أرقام فقط',
            'phone.digits_between' => 'رقم الجوال غير صحيح',
            'phone.unique' => 'رقم الجوال مسجل مسبقاً',
            'email.required' => 'البريد الإلكتروني مطلوب',
            'email.email' => 'البريد الإلكتروني غير صحيح',
            'email.unique' => 'البريد الإلكتروني مسجل مسبقاً',
            'gender.required' => 'الجنس مطلوب',
            'gender.in' => 'الجنس غير صحيح',
            'city_id.required' => 'المدينة مطلوبة',
            'city_id.exists' => 'المدينة غير موجودة',
            'district_name.required' => 'المنطقة مطلوبة',
            'district_name.string' => 'اسم المنطقة غير صالح',
            'password.required' => 'كلمة المرور مطلوبة',
            'password.min' => 'كلمة المرور يجب أن تكون 6 أحرف على الأقل',
            'password.confirmed' => 'كلمة المرور غير متطابقة',
            'password_confirmation.required' => 'تأكيد كلمة المرور مطلوب',
        ];
    }
}

