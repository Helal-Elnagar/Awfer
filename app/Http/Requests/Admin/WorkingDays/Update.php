<?php

namespace App\Http\Requests\Admin\WorkingDays;

use Illuminate\Foundation\Http\FormRequest;

class Update extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $days = $this->input('days', []);

        foreach ($days as $key => $dayData) {
            if (!isset($dayData['morning_from']) && isset($dayData['morning_begin'])) {
                $days[$key]['morning_from'] = $dayData['morning_begin'];
            }

            if (!isset($dayData['morning_to']) && isset($dayData['morning_end'])) {
                $days[$key]['morning_to'] = $dayData['morning_end'];
            }

            if (!isset($dayData['evening_from']) && isset($dayData['evening_begin'])) {
                $days[$key]['evening_from'] = $dayData['evening_begin'];
            }

            if (!isset($dayData['evening_to']) && isset($dayData['evening_end'])) {
                $days[$key]['evening_to'] = $dayData['evening_end'];
            }
        }

        $this->merge(['days' => $days]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'days' => ['required', 'array'],
            'days.*.day' => ['required', 'in:sunday,monday,tuesday,wednesday,thursday,friday,saturday'],
            'days.*.is_working' => ['nullable', 'boolean'],
            'days.*.morning_from' => ['nullable', 'date_format:H:i'],
            'days.*.morning_to' => ['nullable', 'date_format:H:i'],
            'days.*.morning_begin' => ['nullable', 'date_format:H:i'],
            'days.*.morning_end' => ['nullable', 'date_format:H:i'],
            'days.*.evening_from' => ['nullable', 'date_format:H:i'],
            'days.*.evening_to' => ['nullable', 'date_format:H:i'],
            'days.*.evening_begin' => ['nullable', 'date_format:H:i'],
            'days.*.evening_end' => ['nullable', 'date_format:H:i'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            foreach ($this->input('days', []) as $index => $dayData) {
                $isWorking = (bool) ($dayData['is_working'] ?? false);

                if (!$isWorking) {
                    continue;
                }

                $this->validatePeriodOrder($validator, $dayData, $index, 'morning');
                $this->validatePeriodOrder($validator, $dayData, $index, 'evening');
            }
        });
    }

    private function validatePeriodOrder($validator, array $dayData, int|string $index, string $period): void
    {
        $from = $dayData[$period . '_from'] ?? ($dayData[$period . '_begin'] ?? null);
        $to = $dayData[$period . '_to'] ?? ($dayData[$period . '_end'] ?? null);

        if (is_null($from) xor is_null($to)) {
            $validator->errors()->add('days.' . $index . '.' . $period . '_from', __('validation.required'));
            return;
        }

        if (!is_null($from) && !is_null($to) && $from >= $to) {
            $validator->errors()->add('days.' . $index . '.' . $period . '_from', __('admin.start_time_must_be_before_end_time'));
        }
    }
}
