<?php

namespace App\Http\Requests\Admin;

use App\Models\EmployeeFixedShift;
use Illuminate\Foundation\Http\FormRequest;

class EndEmployeeFixedShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        $fixedShift = $this->route('fixed_shift');

        return $fixedShift instanceof EmployeeFixedShift
            && $this->user()->can('update', $fixedShift);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $fixedShift = $this->route('fixed_shift');

        return [
            // Ngày kết thúc có thể nằm trong quá khứ — ghi nhận một thỏa thuận
            // đã ngưng từ trước là việc bình thường — nhưng không được sớm hơn
            // ngày bắt đầu, vì khoảng đó sẽ không phủ ngày nào.
            'effective_to' => [
                'required',
                'date',
                'after_or_equal:'.$fixedShift->effective_from->toDateString(),
            ],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['effective_to' => 'ngày kết thúc'];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['effective_to.after_or_equal' => 'Ngày kết thúc không được trước ngày hiệu lực.'];
    }
}
