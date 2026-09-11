<section class="card">
    <div class="card-header">
        <h2 class="neo-display fs-5 mb-0">Ghi nhận ca làm</h2>
        <p class="mb-0 small text-body-secondary">Dành cho trường hợp ngoại lệ. Ca thường do nhân viên tự chấm bằng GPS. Chỉ ca "Đi làm" và "Đi trễ" được tính lương ca.</p>
    </div>

    <form method="POST" action="{{ route('admin.attendance.store') }}" class="card-body">
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

            <x-admin.field name="work_date" label="Ngày làm" required>
                <input class="form-control @error('work_date') is-invalid @enderror" id="work_date" type="date" name="work_date"
                       value="{{ old('work_date', now()->toDateString()) }}" required>
            </x-admin.field>

            <x-admin.field name="shift_name" label="Tên ca" required>
                <input class="form-control @error('shift_name') is-invalid @enderror" id="shift_name" name="shift_name"
                       value="{{ old('shift_name', 'Ca chính') }}" required>
            </x-admin.field>

            <x-admin.field name="shift_value" label="Hệ số ca" required>
                <input class="form-control text-end neo-num @error('shift_value') is-invalid @enderror" id="shift_value"
                       type="number" step="0.25" min="0" max="10" name="shift_value" value="{{ old('shift_value', 1) }}" required>
            </x-admin.field>

            <x-admin.field name="status" label="Trạng thái" required>
                <select class="form-select @error('status') is-invalid @enderror" id="status_new" name="status" required>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', 'present') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </x-admin.field>

            <x-admin.field name="checked_in_at" label="Giờ vào">
                <input class="form-control @error('checked_in_at') is-invalid @enderror" id="checked_in_at"
                       type="datetime-local" name="checked_in_at" value="{{ old('checked_in_at') }}">
            </x-admin.field>

            <x-admin.field name="checked_out_at" label="Giờ ra">
                <input class="form-control @error('checked_out_at') is-invalid @enderror" id="checked_out_at"
                       type="datetime-local" name="checked_out_at" value="{{ old('checked_out_at') }}">
            </x-admin.field>

            <x-admin.field name="reason" label="Lý do nhập tay" col="col-12" required
                           help="Lưu vào nhật ký chỉnh sửa để sau này còn tra được vì sao ca này không do nhân viên tự chấm.">
                <input class="form-control @error('reason') is-invalid @enderror" id="reason" name="reason"
                       maxlength="255" required value="{{ old('reason') }}" placeholder="Nhân viên quên bấm vào ca">
            </x-admin.field>

            <x-admin.field name="note" label="Ghi chú" col="col-12">
                <textarea class="form-control @error('note') is-invalid @enderror" id="note" name="note" rows="2">{{ old('note') }}</textarea>
            </x-admin.field>
        </div>

        <div class="d-grid d-lg-flex justify-content-lg-end mt-3">
            <x-admin.submit-button label="Ghi nhận ca" />
        </div>
    </form>
</section>
