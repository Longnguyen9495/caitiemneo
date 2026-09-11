<x-layouts.admin title="Tạo bảng lương" heading="Nhân sự & lương">
    @include('admin.partials.staff-nav')

    <section class="admin-form-panel">
        <x-admin.page-header
            title="Tạo bảng lương"
            description="Hệ thống sẽ tự tổng hợp số ca và hoa hồng của kỳ ngay khi tạo."
            :breadcrumbs="['Bảng lương' => route('admin.payrolls.index'), 'Tạo mới' => null]"
        />

        <form method="POST" action="{{ route('admin.payrolls.store') }}" class="admin-form">
            @csrf

            <label>
                Nhân viên
                <select name="employee_id" required>
                    <option value="">Chọn nhân viên</option>
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->id }}" @selected((string) old('employee_id') === (string) $employee->id)>{{ $employee->name }}</option>
                    @endforeach
                </select>
                @error('employee_id')<small>{{ $message }}</small>@enderror
            </label>

            <label>
                Chi nhánh trả lương
                <select name="paying_branch_id">
                    <option value="">Theo chi nhánh chính của nhân viên</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected((string) old('paying_branch_id') === (string) $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
                @error('paying_branch_id')<small>{{ $message }}</small>@enderror
            </label>

            <label>
                Từ ngày
                <input type="date" name="period_start" value="{{ old('period_start', $periodStart->toDateString()) }}" required>
                @error('period_start')<small>{{ $message }}</small>@enderror
            </label>

            <label>
                Đến ngày
                <input type="date" name="period_end" value="{{ old('period_end', $periodEnd->toDateString()) }}" required>
                @error('period_end')<small>{{ $message }}</small>@enderror
            </label>

            <label>
                Phụ cấp (VNĐ)
                <x-admin.money-input name="adjustment" :value="old('adjustment', 0)" />
                @error('adjustment')<small>{{ $message }}</small>@enderror
            </label>

            <label>
                Khấu trừ (VNĐ)
                <x-admin.money-input name="deduction" :value="old('deduction', 0)" />
                @error('deduction')<small>{{ $message }}</small>@enderror
            </label>

            <label class="admin-form-wide">
                Ghi chú
                <textarea name="note" rows="3">{{ old('note') }}</textarea>
                @error('note')<small>{{ $message }}</small>@enderror
            </label>

            <div class="admin-form-actions admin-form-wide">
                <a href="{{ route('admin.payrolls.index') }}">Hủy</a>
                <x-admin.submit-button label="Tạo bảng lương nháp" />
            </div>
        </form>
    </section>
</x-layouts.admin>
