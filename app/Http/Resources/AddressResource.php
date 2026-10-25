<?php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AddressResource extends JsonResource
{
  

    public function toArray($request)
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'address_name' =>$this->address_name,
            'district_name' =>$this->district_name,
            'primary_phone' => $user?->phone ?? null,
            'alternative_phone' => $this->phone,
            'country_code' => $this->country_code,
            'district' => [
                'id' => $this->districts_id,
                'name' => $this->district_name ?? optional($this->district)->getTranslation('name', app()->getLocale()),
            ],
            'city' => [
                'id' => $this->city_id,
                'name' => optional($this->city)->name,
                'delivery_fee' => (float) optional($this->city)->delivery_fee,
            ],
            'note'=>$this->note,
            'description' => $this->description,
            'building_number' => $this->building_number,
            'floor_number' => $this->floor_number,
            'address_type' =>$this->address_type,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'is_default' => $this->is_default,
            'created_at' => $this->created_at,
        ];
    }
}
