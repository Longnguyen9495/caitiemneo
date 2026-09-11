<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EmployeeRequest;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\View\View;

class EmployeeController extends Controller
{
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
        ]);
    }

    public function store(EmployeeRequest $request): RedirectResponse
    {
        User::query()->create($request->validated() + ['email_verified_at' => now()]);

        return redirect()->route('admin.employees.index')->with('success', 'Đã tạo tài khoản nhân sự.');
    }

    public function edit(User $employee): View
    {
        $this->authorize('update', $employee);

        $employee->load(['branchAssignments.branch', 'compensationProfiles.branch']);

        return view('admin.employees.form', [
            'employee' => $employee,
            'roles' => UserRole::options(),
            'branches' => Branch::query()->active()->orderBy('code')->get(),
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

        $employee->update($data);

        return redirect()->route('admin.employees.index')->with('success', 'Đã cập nhật tài khoản nhân sự.');
    }
}
