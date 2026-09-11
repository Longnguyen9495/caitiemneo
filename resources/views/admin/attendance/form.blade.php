<x-layouts.admin title="Sửa chấm công" heading="Nhân sự & lương">
    @include('admin.partials.staff-nav')

    <section class="admin-form-panel">
        <x-admin.page-header
            title="Chỉnh sửa ca chấm công"
            :description="$record->employee?->name.' · '.$record->work_date->format('d/m/Y')"
            :breadcrumbs="['Chấm công' => route('admin.attendance.index', ['month' => $record->work_date->format('Y-m')]), 'Chỉnh sửa' => null]"
        />

        <form method="POST" action="{{ route('admin.attendance.update', $record) }}" class="admin-form">
            @csrf
            @method('PUT')

            <label>
                Nhân viên
                <select name="employee_id" required>
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->id }}" @selected((string) old('employee_id', $record->employee_id) === (string) $employee->id)>{{ $employee->name }}</option>
                    @endforeach
                </select>
                @error('employee_id')<small>{{ $message }}</small>@enderror
            </label>

            <label>
                Ngày làm
                <input type="date" name="work_date" value="{{ old('work_date', $record->work_date->toDateString()) }}" required>
                @error('work_date')<small>{{ $message }}</small>@enderror
            </label>

            <label>
                Tên ca
                <input name="shift_name" value="{{ old('shift_name', $record->shift_name) }}" required>
                @error('shift_name')<small>{{ $message }}</small>@enderror
            </label>

            <label>
                Hệ số ca
                <input class="admin-money-input" type="number" step="0.25" min="0" max="10" name="shift_value" value="{{ old('shift_value', $record->shift_value) }}" required>
                @error('shift_value')<small>{{ $message }}</small>@enderror
            </label>

            <label>
                Trạng thái
                <select name="status" required>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', $record->status->value) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('status')<small>{{ $message }}</small>@enderror
            </label>

            <label>
                Giờ vào
                <input type="datetime-local" name="checked_in_at" value="{{ old('checked_in_at', $record->checked_in_at?->format('Y-m-d\TH:i')) }}">
                @error('checked_in_at')<small>{{ $message }}</small>@enderror
            </label>

            <label>
                Giờ ra
                <input type="datetime-local" name="checked_out_at" value="{{ old('checked_out_at', $record->checked_out_at?->format('Y-m-d\TH:i')) }}">
                @error('checked_out_at')<small>{{ $message }}</small>@enderror
            </label>

            <label class="admin-form-wide">
                Ghi chú
                <textarea name="note" rows="2">{{ old('note', $record->note) }}</textarea>
                @error('note')<small>{{ $message }}</small>@enderror
            </label>

            <div class="admin-form-actions admin-form-wide">
                <a href="{{ route('admin.attendance.index', ['month' => $record->work_date->format('Y-m')]) }}">Hủy</a>
                <x-admin.submit-button label="Lưu thay đổi" />
            </div>
        </form>
    </section>
</x-layouts.admin>
