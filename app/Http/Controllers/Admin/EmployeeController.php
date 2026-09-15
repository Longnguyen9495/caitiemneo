<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Employees\AssignEmployeeToBranchAction;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EmployeeRequest;
use App\Models\Branch;
use App\Models\EmployeeCompensationProfile;
use App\Models\User;
use App\Services\Auth\SessionRevoker;
use App\Support\BranchContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    public function __construct(
        private SessionRevoker $sessionRevoker,
        private BranchContext $branchContext,
        private AssignEmployeeToBranchAction $assignToBranch,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $employees = User::query()
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $term = '%'.$request->string('search')->toString().'%';

                $query->where(fn (Builder $inner) => $inner
                    ->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term));
            })
            ->when($request->filled('role'), fn (Builder $query) => $query->where('role', $request->string('role')->toString()))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('is_active', $request->string('status')->toString() === 'active'))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.employees.index', [
            'employees' => $employees,
            'roles' => UserRole::options(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('admin.employees.form', [
            'employee' => new User(['role' => UserRole::Employee, 'is_active' => true]),
            'roles' => UserRole::options(),
            'branches' => Branch::query()->active()->orderBy('code')->get(),
            'defaultBranchId' => $this->branchContext->requireWritableBranchId(),
        ]);
    }

    /**
     * Create the account and its first posting together.
     *
     * Splitting the two used to leave a usable account that could not enter the
     * admin area, because access is decided by the posting and not by the role.
     */
    public function store(EmployeeRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $branchId = (int) $data['branch_id'];

        DB::transaction(function () use ($data, $branchId, $request): void {
            $employee = User::query()->create(Arr::except($data, ['branch_id']) + ['email_verified_at' => now()]);

            $this->syncCurrentCompensationProfile($employee, $data, $request->user()->getKey());

            $this->assignToBranch->handle(
                employee: $employee,
                branchId: $branchId,
                startsOn: now()->toDateString(),
                actor: $request->user(),
            );
        });

        return redirect()->route('admin.employees.index')->with('success', 'Đã tạo tài khoản nhân sự và phân công chi nhánh.');
    }

    public function edit(User $employee): View
    {
        $this->authorize('update', $employee);

        $employee->load(['branchAssignments.branch', 'compensationProfiles.branch']);

        return view('admin.employees.form', [
            'employee' => $employee,
            'roles' => UserRole::options(),
            'branches' => Branch::query()->active()->orderBy('code')->get(),
            'defaultBranchId' => $this->branchContext->requireWritableBranchId(),
        ]);
    }

    public function update(EmployeeRequest $request, User $employee): RedirectResponse
    {
        $data = $request->validated();

        if (blank($data['password'] ?? null)) {
            $data = Arr::except($data, ['password']);
        }

        if (! $data['is_active'] && $employee->is_active && ! $request->user()->can('deactivate', $employee)) {
            return back()
                ->withInput()
                ->withErrors(['is_active' => 'Không thể vô hiệu hóa chủ tiệm cuối cùng của hệ thống.']);
        }

        $trustChanged = $this->trustChanged($employee, $data);

        DB::transaction(function () use ($employee, $data, $request): void {
            $employee->update($data);
            $this->syncCurrentCompensationProfile($employee, $data, $request->user()->getKey());
        });

        // Vô hiệu hóa, đổi role hay đặt lại mật khẩu đều là lời khẳng định rằng
        // trạng thái cũ của tài khoản không còn đáng tin. Nếu các phiên đang mở
        // vẫn chạy thì những thay đổi đó chỉ có hiệu lực khi người kia tình cờ
        // đăng xuất — đúng lúc không nên chờ đợi nhất.
        if ($trustChanged) {
            $this->sessionRevoker->revokeFor($employee, $request->session()->getId());
        }

        return redirect()->route('admin.employees.index')->with('success', 'Đã cập nhật tài khoản nhân sự.');
    }

    /**
     * Các trường lương cũ trên users chỉ phục vụ form tương thích; hóa đơn và
     * bảng lương luôn lấy snapshot từ hồ sơ có hiệu lực theo ngày. Khi người
     * quản trị đổi mức hiện tại, đóng hồ sơ toàn hệ thống cũ và mở bản ghi mới
     * từ hôm nay để số liệu đã chốt trong quá khứ không bị viết lại.
     *
     * @param  array<string, mixed>  $data
     */
    private function syncCurrentCompensationProfile(User $employee, array $data, int $actorId): void
    {
        $today = now()->toDateString();
        $current = EmployeeCompensationProfile::query()
            ->where('user_id', $employee->getKey())
            ->whereNull('branch_id')
            ->effectiveOn($today)
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();

        $rates = [
            'base_salary' => $data['base_salary'],
            'shift_rate' => $data['shift_rate'],
            'regular_commission_rate' => $data['commission_rate'],
            'overtime_commission_rate' => $data['commission_rate'],
        ];

        if ($current?->effective_from?->isSameDay($today)) {
            $current->update($rates);

            return;
        }

        if ($current !== null) {
            $current->update(['effective_to' => now()->subDay()->toDateString()]);
        }

        $employee->compensationProfiles()->create($rates + [
            'branch_id' => null,
            'effective_from' => $today,
            'effective_to' => null,
            'created_by' => $actorId,
        ]);
    }

    /**
     * Thay đổi khiến các phiên đang mở của tài khoản không còn đáng tin.
     *
     * @param  array<string, mixed>  $data
     */
    private function trustChanged(User $employee, array $data): bool
    {
        if (array_key_exists('password', $data) && filled($data['password'])) {
            return true;
        }

        if (array_key_exists('is_active', $data) && ! $data['is_active'] && $employee->is_active) {
            return true;
        }

        return array_key_exists('role', $data)
            && (string) $data['role'] !== $employee->role->value;
    }
}
