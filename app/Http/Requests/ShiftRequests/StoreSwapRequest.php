<?php

namespace App\Http\Requests\ShiftRequests;

use App\Models\ShiftAssignment;
use App\Models\ShiftRequest;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSwapRequest extends FormRequest
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
            'recipient_id' => ['required', 'integer', 'different:requester_id', Rule::exists(User::class, 'id')],
            'counter_shift_assignment_id' => ['required', 'integer', 'different:shift_assignment_id', Rule::exists(ShiftAssignment::class, 'id')],
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'shift_assignment_id' => 'ca làm của bạn',
            'recipient_id' => 'người nhận đổi ca',
            'counter_shift_assignment_id' => 'ca đối ứng',
            'reason' => 'lý do',
        ];
    }
}
