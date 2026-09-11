<?php

namespace App\Http\Requests\Admin;

use App\Models\Branch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class BranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        $branch = $this->route('branch');

        return $branch instanceof Branch
            ? $this->user()->can('update', $branch)
            : $this->user()->can('create', Branch::class);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $branch = $this->route('branch');

        return [
            'code' => [
                'required', 'string', 'max:30', 'alpha_dash',
                Rule::unique(Branch::class, 'code')->ignore($branch instanceof Branch ? $branch->getKey() : null),
            ],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'attendance_radius_meters' => [
                'required', 'integer',
                'min:'.config('attendance.min_radius_meters', 20),
                'max:'.config('attendance.max_radius_meters', 2000),
            ],
            'attendance_accuracy_limit_meters' => [
                'required', 'integer',
                'min:'.config('attendance.min_radius_meters', 20),
                'max:'.config('attendance.max_radius_meters', 2000),
            ],
            'gps_attendance_enabled' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'gps_attendance_enabled' => $this->boolean('gps_attendance_enabled'),
            'latitude' => $this->filled('latitude') ? $this->input('latitude') : null,
            'longitude' => $this->filled('longitude') ? $this->input('longitude') : null,
            'attendance_radius_meters' => $this->input('attendance_radius_meters')
                ?: config('attendance.default_radius_meters', 100),
            'attendance_accuracy_limit_meters' => $this->input('attendance_accuracy_limit_meters')
                ?: config('attendance.default_accuracy_limit_meters', 150),
        ]);
    }

    /**
     * A geofence with no centre would let anybody clock in from anywhere.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->boolean('gps_attendance_enabled') && ! $this->filled('latitude')) {
                $validator->errors()->add('latitude', 'Hãy nhập tọa độ cửa hàng trước khi bật chấm công GPS.');
            }
        });
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'code' => 'mã chi nhánh',
            'name' => 'tên chi nhánh',
            'address' => 'địa chỉ',
            'phone' => 'số điện thoại',
            'latitude' => 'vĩ độ',
            'longitude' => 'kinh độ',
            'attendance_radius_meters' => 'bán kính chấm công',
            'attendance_accuracy_limit_meters' => 'ngưỡng sai số GPS',
        ];
    }
}
