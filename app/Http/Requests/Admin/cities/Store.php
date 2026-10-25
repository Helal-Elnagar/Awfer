<?php

namespace App\Http\Requests\Admin\cities;

use Illuminate\Foundation\Http\FormRequest;

class store extends FormRequest {
    public function authorize() {
        return true;
    }

    public function rules() {
        return [
            'name'      => ['required', 'array', function($attribute, $value, $fail) {
                if (!collect($value)->filter()->count()) {
                    $fail(__('admin.at_least_one_name_required'));
                }
            }],
            'name.*'    => 'nullable|string|max:191',
            'region_id' => 'required|exists:regions,id',
            'country_id' => 'smoetmies|exists:countries,id',
            'delivery_fee' => 'required|numeric|min:0',
            'boundary' => ['nullable', 'string', function ($attribute, $value, $fail) {
                if (trim((string) $value) === '') {
                    return;
                }
                $decoded = json_decode($value, true);
                if (!is_array($decoded)) {
                    $fail(__('admin.boundary_must_be_valid_polygon'));
                    return;
                }
                // Unwrap an outer ring if present (e.g. [[[lat,lng],...]] or [[{lat,lng},...]])
                $first = $decoded[0] ?? null;
                if (is_array($first) && isset($first[0]) && !isset($first['lat']) && (is_array($first[0]) || is_object($first[0]))) {
                    $decoded = $first;
                }
                if (count($decoded) < 3) {
                    $fail(__('admin.boundary_must_be_valid_polygon'));
                    return;
                }
                foreach ($decoded as $point) {
                    if (!is_array($point) && !is_object($point)) {
                        $fail(__('admin.boundary_must_be_valid_polygon'));
                        return;
                    }
                    $p = (array) $point;
                    $lat = isset($p['lat']) ? $p['lat'] : ($p[0] ?? null);
                    $lng = isset($p['lng']) ? $p['lng'] : ($p[1] ?? null);
                    if ($lat === null || $lng === null || !is_numeric($lat) || !is_numeric($lng) || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
                        $fail(__('admin.boundary_must_be_valid_polygon'));
                        return;
                    }
                }
            }],

        ];
    }
}
