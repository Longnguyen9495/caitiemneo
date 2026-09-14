<?php

namespace App\Http\Requests\ShiftRequests;

use App\Models\ShiftAssignment;
use App\Models\ShiftRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ShiftRequest::class) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'shift_assignment_id' => ['required', 'integer', Rule::exists(ShiftAssignment::class, 'id')],
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'shift_assignment_id' => 'ca làm cần xin nghỉ',
            'reason' => 'lý do',
        ];
    }
}
