<?php

namespace App\Actions\Employees;

use App\Models\EmployeeBranchAssignment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Post one employee to one branch from a given date.
 *
 * Shared by the create-account screen and the assignment panel so that both
 * doors into the posting history close the previous primary the same way.
 */
class AssignEmployeeToBranchAction
{
    public function handle(
        User $employee,
        int $branchId,
        string $startsOn,
        ?string $endsOn = null,
        bool $isPrimary = true,
        ?User $actor = null,
    ): EmployeeBranchAssignment {
        return DB::transaction(function () use ($employee, $branchId, $startsOn, $endsOn, $isPrimary, $actor): EmployeeBranchAssignment {
            // One primary posting at a time: close the previous one the day
            // before the new one starts.
            if ($isPrimary) {
                /*
                 * Chỉ đóng được phân công đã bắt đầu trước ngày này.
                 *
                 * Đóng một phân công bắt đầu muộn hơn sẽ ghi `ends_on` nằm
                 * trước `starts_on` của chính nó — một khoảng ngày ngược, không
                 * phủ ngày nào, khiến người đó bỗng không thuộc chi nhánh nào
                 * trong quãng lẽ ra đã được sắp sẵn. Gặp trường hợp đó thì từ
                 * chối và để người phân công tự quyết, thay vì lặng lẽ làm hỏng.
                 */
                $laterPosting = EmployeeBranchAssignment::query()
                    ->where('user_id', $employee->getKey())
                    ->where('is_primary', true)
                    ->whereNull('ends_on')
                    ->whereDate('starts_on', '>=', $startsOn)
                    ->exists();

                if ($laterPosting) {
                    throw ValidationException::withMessages([
                        'starts_on' => 'Nhân viên đã có phân công chính thức bắt đầu từ ngày này trở đi. Hãy kết thúc phân công đó trước.',
                    ]);
                }

                EmployeeBranchAssignment::query()
                    ->where('user_id', $employee->getKey())
                    ->where('is_primary', true)
                    ->whereNull('ends_on')
                    ->whereDate('starts_on', '<', $startsOn)
                    ->update(['ends_on' => Carbon::parse($startsOn)->subDay()->toDateString()]);
            }

            return EmployeeBranchAssignment::query()->create([
                'branch_id' => $branchId,
                'user_id' => $employee->getKey(),
                'is_primary' => $isPrimary,
                'starts_on' => $startsOn,
                'ends_on' => $endsOn,
                'created_by' => $actor?->getKey(),
            ]);
        });
    }
}
