<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <h2>Ghi nhận ca làm</h2>
            <p>Hệ số ca dùng để tính lương ca; chỉ ca "Đi làm" và "Đi trễ" được tính.</p>
        </div>
    </header>

    <form method="POST" action="{{ route('admin.attendance.store') }}" class="admin-form" style="padding: 1.25rem 1.4rem;">
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
            Ngày làm
            <input type="date" name="work_date" value="{{ old('work_date', now()->toDateString()) }}" required>
            @error('work_date')<small>{{ $message }}</small>@enderror
        </label>

        <label>
            Tên ca
            <input name="shift_name" value="{{ old('shift_name', 'Ca chính') }}" required>
            @error('shift_name')<small>{{ $message }}</small>@enderror
        </label>

        <label>
            Hệ số ca
            <input class="admin-money-input" type="number" step="0.25" min="0" max="10" name="shift_value" value="{{ old('shift_value', 1) }}" required>
            @error('shift_value')<small>{{ $message }}</small>@enderror
        </label>

        <label>
            Trạng thái
            <select name="status" required>
                @foreach ($statuses as $value => $label)
                    <option value="{{ $value }}" @selected(old('status', 'present') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('status')<small>{{ $message }}</small>@enderror
        </label>

        <label>
            Giờ vào
            <input type="datetime-local" name="checked_in_at" value="{{ old('checked_in_at') }}">
            @error('checked_in_at')<small>{{ $message }}</small>@enderror
        </label>

        <label>
            Giờ ra
            <input type="datetime-local" name="checked_out_at" value="{{ old('checked_out_at') }}">
            @error('checked_out_at')<small>{{ $message }}</small>@enderror
        </label>

        <label class="admin-form-wide">
            Ghi chú
            <textarea name="note" rows="2">{{ old('note') }}</textarea>
            @error('note')<small>{{ $message }}</small>@enderror
        </label>

        <div class="admin-form-actions admin-form-wide">
            <x-admin.submit-button label="Ghi nhận ca" />
        </div>
    </form>
</section>
