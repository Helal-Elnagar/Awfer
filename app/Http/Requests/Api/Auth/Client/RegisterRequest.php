<?php

namespace App\Http\Requests\Api\Auth\Client;

use App\Http\Requests\Api\BaseApiRequest;
use App\Models\District;
use Illuminate\Http\Request;

class RegisterRequest extends BaseApiRequest {
  
  public function __construct(Request $request) {
    $request['phone']        = fixPhone($request['phone']);
    $request['country_code'] = fixPhone($request['country_code']);
  }

  public function rules() {
    return [
      'name'         => 'required|max:50',
      'country_code' => 'required|numeric|digits_between:2,5',
      'phone'        => 'required|numeric|digits_between:8,10|unique:users,phone,NULL,id,deleted_at,NULL',
      'email'        => 'sometimes|nullable|email|unique:users,email,NULL,id,deleted_at,NULL|max:50',
      'password'     => 'required|min:6|max:100|confirmed',
      'image'        => 'nullable',
      'type'         => 'required|in:client,delivery',
      'city_id'      => 'required|exists:cities,id',
      'gender' => 'nullable|in:male,female',
       'region_id'                 => 'nullable|exists:regions,id',
      'district_name'      => 'required|string|max:255',


    ];
  }

  protected function prepareForValidation(): void
  {
    if (!$this->filled('district_name') && $this->filled('district_id')) {
      $districtName = District::query()->where('id', $this->input('district_id'))->value('name');
      $this->merge(['district_name' => $districtName]);
    }
  }

}
