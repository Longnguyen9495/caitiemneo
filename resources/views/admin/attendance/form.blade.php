<x-layouts.admin title="Sửa chấm công" heading="Chấm công">
    @include('admin.partials.staff-nav')

    <x-admin.page-header
        title="Chỉnh sửa ca chấm công"
        :description="$record->employee?->name.' · '.$record->work_date->format('d/m/Y')"
        :breadcrumbs="['Chấm công' => route('admin.attendance.index', ['month' => $record->work_date->format('Y-m')]), 'Chỉnh sửa' => null]"
    />

    @if ($isLocked)
        <div class="alert alert-warning border-0" role="alert">
            Ca này nằm trong một bảng lương đã chốt. Mọi thay đổi sẽ bị từ chối để giữ nguyên số đã trả.
        </div>
    @endif

    @include('admin.attendance.partials.gps-evidence', ['record' => $record])

    <form method="POST" action="{{ route('admin.attendance.update', $record) }}" class="card p-3 p-lg-4">
        @csrf
        @method('PUT')

        <div class="row g-3">
            <x-admin.field name="employee_id" label="Nhân viên" required>
                <select class="form-select @error('employee_id') is-invalid @enderror" id="employee_id" name="employee_id" required>
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->id }}" @selected((string) old('employee_id', $record->employee_id) === (string) $employee->id)>{{ $employee->name }}</option>
                    @endforeach
                </select>
            </x-admin.field>

            <x-admin.field name="work_date" label="Ngày làm" required>
                <input class="form-control @error('work_date') is-invalid @enderror" id="work_date" type="date" name="work_date"
                       value="{{ old('work_date', $record->work_date->toDateString()) }}" required>
            </x-admin.field>

            <x-admin.field name="shift_name" label="Tên ca" required>
                <input class="form-control @error('shift_name') is-invalid @enderror" id="shift_name" name="shift_name"
                       value="{{ old('shift_name', $record->shift_name) }}" required>
            </x-admin.field>

            <x-admin.field name="shift_value" label="Hệ số ca" required>
                <input class="form-control text-end neo-num @error('shift_value') is-invalid @enderror" id="shift_value"
                       type="number" step="0.25" min="0" max="10" name="shift_value"
                       value="{{ old('shift_value', $record->shift_value) }}" required>
            </x-admin.field>

            <x-admin.field name="status" label="Trạng thái" required>
                <select class="form-select @error('status') is-invalid @enderror" id="status" name="status" required>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', $record->status->value) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </x-admin.field>

            <x-admin.field name="checked_in_at" label="Giờ vào">
                <input class="form-control @error('checked_in_at') is-invalid @enderror" id="checked_in_at" type="datetime-local"
                       name="checked_in_at" value="{{ old('checked_in_at', $record->checked_in_at?->format('Y-m-d\TH:i')) }}">
            </x-admin.field>

            <x-admin.field name="checked_out_at" label="Giờ ra">
                <input class="form-control @error('checked_out_at') is-invalid @enderror" id="checked_out_at" type="datetime-local"
                       name="checked_out_at" value="{{ old('checked_out_at', $record->checked_out_at?->format('Y-m-d\TH:i')) }}">
            </x-admin.field>

            <x-admin.field name="reason" label="Lý do chỉnh sửa" col="col-12" required
                           help="Bắt buộc: giá trị trước và sau sẽ được lưu kèm lý do này vào nhật ký.">
                <input class="form-control @error('reason') is-invalid @enderror" id="reason" name="reason"
                       maxlength="255" required value="{{ old('reason') }}" placeholder="Nhân viên báo quên bấm ra ca">
            </x-admin.field>

            <x-admin.field name="note" label="Ghi chú" col="col-12">
                <textarea class="form-control @error('note') is-invalid @enderror" id="note" name="note" rows="2">{{ old('note', $record->note) }}</textarea>
            </x-admin.field>
        </div>

        <div class="neo-formbar">
            <a href="{{ route('admin.attendance.index', ['month' => $record->work_date->format('Y-m')]) }}" class="btn btn-light">Hủy</a>
            <x-admin.submit-button label="Lưu thay đổi" />
        </div>
    </form>

    @include('admin.attendance.partials.audit-trail', ['logs' => $record->auditLogs])
</x-layouts.admin>
