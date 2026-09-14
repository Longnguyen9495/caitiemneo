<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\ResolvesBranch;
use App\Models\MonthlyPaidLeaveDay;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ScheduleMonthlyPaidLeaveDaysRequest extends FormRequest
{
    use ResolvesBranch;

    public function authorize(): bool
    {
        return $this->user()->can('create', MonthlyPaidLeaveDay::class);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'branch_id' => $this->branchRules(),
            'employee_id' => ['required', 'integer', Rule::exists(User::class, 'id')],
            'month' => ['required', 'date_format:Y-m'],
            'leave_dates' => ['required', 'array', 'size:2'],
            'leave_dates.*' => ['required', 'date', 'distinct'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->mergeResolvedBranch();
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'branch_id' => 'chi nhánh',
            'employee_id' => 'nhân viên',
            'month' => 'tháng xếp nghỉ',
            'leave_dates' => 'ngày nghỉ hưởng lương',
            'leave_dates.*' => 'ngày nghỉ hưởng lương',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return array_merge($this->branchMessages(), [
            'leave_dates.size' => 'Mỗi nhân viên phải được xếp đúng 2 ngày nghỉ hưởng lương trong tháng.',
            'leave_dates.*.distinct' => 'Hai ngày nghỉ hưởng lương không được trùng nhau.',
        ]);
    }
}
