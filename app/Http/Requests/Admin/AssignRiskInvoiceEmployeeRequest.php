<?php

namespace App\Http\Requests\Admin;

use App\Models\RiskFlag;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignRiskInvoiceEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $riskFlag = $this->route('risk_flag');

        return $riskFlag instanceof RiskFlag
            && $this->user()?->can('review', $riskFlag) === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        /** @var RiskFlag|null $riskFlag */
        $riskFlag = $this->route('risk_flag');

        return [
            'employee_id' => [
                'required',
                'integer',
                Rule::exists(User::class, 'id'),
                function (string $attribute, mixed $value, \Closure $fail) use ($riskFlag): void {
                    $employee = User::query()->find($value);
                    $isPostedToBranch = $employee?->is_active && $employee->branchAssignments()
                        ->where('branch_id', $riskFlag?->branch_id)
                        ->whereDate('starts_on', '<=', now()->toDateString())
                        ->where(fn ($period) => $period
                            ->whereNull('ends_on')
                            ->orWhereDate('ends_on', '>=', now()->toDateString()))
                        ->exists();

                    if (! $isPostedToBranch) {
                        $fail('Nhân viên được chọn không làm việc tại chi nhánh của hóa đơn.');
                    }
                },
            ],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'employee_id' => 'nhân viên nhận hoa hồng',
        ];
    }
}
