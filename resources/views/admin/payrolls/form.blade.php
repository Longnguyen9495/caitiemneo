<x-layouts.admin title="Tạo bảng lương" heading="Tạo bảng lương">
    @include('admin.partials.staff-nav')

    <x-admin.page-header
        title="Tạo bảng lương"
        description="Hệ thống tự tổng hợp số ca, hoa hồng và KPI của kỳ ngay khi tạo."
        :breadcrumbs="['Bảng lương' => route('admin.payrolls.index'), 'Tạo mới' => null]"
    />

    <form method="POST" action="{{ route('admin.payrolls.store') }}" class="card p-3 p-lg-4">
        @csrf

        <div class="row g-3">
            <x-admin.field name="employee_id" label="Nhân viên" required>
                <select class="form-select @error('employee_id') is-invalid @enderror" id="employee_id" name="employee_id" required>
                    <option value="">Chọn nhân viên</option>
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->id }}" @selected((string) old('employee_id') === (string) $employee->id)>{{ $employee->name }}</option>
                    @endforeach
                </select>
            </x-admin.field>

            <x-admin.field name="paying_branch_id" label="Chi nhánh trả lương">
                <select class="form-select @error('paying_branch_id') is-invalid @enderror" id="paying_branch_id" name="paying_branch_id">
                    <option value="">Theo chi nhánh chính</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected((string) old('paying_branch_id') === (string) $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            </x-admin.field>

            <x-admin.field name="period_start" label="Từ ngày" required>
                <input class="form-control @error('period_start') is-invalid @enderror" id="period_start" type="date"
                       name="period_start" value="{{ old('period_start', $periodStart->toDateString()) }}" required>
            </x-admin.field>

            <x-admin.field name="period_end" label="Đến ngày" required>
                <input class="form-control @error('period_end') is-invalid @enderror" id="period_end" type="date"
                       name="period_end" value="{{ old('period_end', $periodEnd->toDateString()) }}" required>
            </x-admin.field>

            <x-admin.field name="adjustment" label="Phụ cấp">
                <x-admin.money-input name="adjustment" :value="old('adjustment', 0)" />
            </x-admin.field>

            <x-admin.field name="deduction" label="Khấu trừ">
                <x-admin.money-input name="deduction" :value="old('deduction', 0)" />
            </x-admin.field>

            <x-admin.field name="note" label="Ghi chú" col="col-12">
                <textarea class="form-control @error('note') is-invalid @enderror" id="note" name="note" rows="3">{{ old('note') }}</textarea>
            </x-admin.field>
        </div>

        <div class="neo-formbar">
            <a href="{{ route('admin.payrolls.index') }}" class="btn btn-light">Hủy</a>
            <x-admin.submit-button label="Tạo bảng lương nháp" />
        </div>
    </form>
</x-layouts.admin>
