<x-layouts.admin :title="$employee->exists ? 'Sửa nhân sự' : 'Thêm nhân sự'" heading="Nhân sự & lương">
    @include('admin.partials.staff-nav')

    <section class="admin-form-panel">
        <x-admin.page-header
            :title="$employee->exists ? $employee->name : 'Thêm tài khoản nhân sự'"
            :description="$employee->exists ? 'Để trống mật khẩu nếu không muốn thay đổi.' : 'Tài khoản mới sẽ đăng nhập bằng email và mật khẩu bên dưới.'"
            :breadcrumbs="['Nhân sự' => route('admin.employees.index'), ($employee->exists ? 'Chỉnh sửa' : 'Thêm mới') => null]"
        />

        <form method="POST" action="{{ $employee->exists ? route('admin.employees.update', $employee) : route('admin.employees.store') }}" class="admin-form">
            @csrf
            @if ($employee->exists)
                @method('PUT')
            @endif

            <label>
                Họ tên
                <input name="name" value="{{ old('name', $employee->name) }}" required autofocus>
                @error('name')<small>{{ $message }}</small>@enderror
            </label>

            <label>
                Tên đăng nhập
                <input name="username" value="{{ old('username', $employee->username) }}" placeholder="Không bắt buộc">
                @error('username')<small>{{ $message }}</small>@enderror
            </label>

            <label>
                Email
                <input type="email" name="email" value="{{ old('email', $employee->email) }}" required>
                @error('email')<small>{{ $message }}</small>@enderror
            </label>

            <label>
                Số điện thoại
                <input name="phone" value="{{ old('phone', $employee->phone) }}" inputmode="tel">
                @error('phone')<small>{{ $message }}</small>@enderror
            </label>

            <label>
                Mật khẩu {{ $employee->exists ? '(để trống nếu giữ nguyên)' : '' }}
                <input type="password" name="password" autocomplete="new-password" @required(! $employee->exists)>
                @error('password')<small>{{ $message }}</small>@enderror
            </label>

            <label>
                Nhập lại mật khẩu
                <input type="password" name="password_confirmation" autocomplete="new-password" @required(! $employee->exists)>
            </label>

            <label>
                Vai trò
                <select name="role" required>
                    @foreach ($roles as $value => $label)
                        <option value="{{ $value }}" @selected(old('role', $employee->role?->value) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('role')<small>{{ $message }}</small>@enderror
            </label>

            <label>
                Lương cứng theo kỳ (VNĐ)
                <x-admin.money-input name="base_salary" :value="$employee->base_salary ?? 0" required />
                @error('base_salary')<small>{{ $message }}</small>@enderror
            </label>

            <label>
                Đơn giá mỗi ca (VNĐ)
                <x-admin.money-input name="shift_rate" :value="$employee->shift_rate ?? 0" required />
                @error('shift_rate')<small>{{ $message }}</small>@enderror
            </label>

            <label>
                Tỷ lệ hoa hồng (%)
                <input class="admin-money-input" type="number" step="0.5" min="0" max="100" name="commission_rate" value="{{ old('commission_rate', $employee->commission_rate ?? 0) }}" required>
                @error('commission_rate')<small>{{ $message }}</small>@enderror
            </label>

            <fieldset class="admin-form-wide">
                <legend>Quyền thao tác</legend>
                <div class="admin-check-grid">
                    <label class="admin-checkbox"><input type="checkbox" name="can_manage_appointments" value="1" @checked(old('can_manage_appointments', $employee->can_manage_appointments))><span>Quản lý lịch hẹn</span></label>
                    <label class="admin-checkbox"><input type="checkbox" name="can_create_invoices" value="1" @checked(old('can_create_invoices', $employee->can_create_invoices))><span>Tạo và thanh toán hóa đơn</span></label>
                    <label class="admin-checkbox"><input type="checkbox" name="can_manage_payroll" value="1" @checked(old('can_manage_payroll', $employee->can_manage_payroll))><span>Quản lý bảng lương (dành cho quản lý)</span></label>
                    <label class="admin-checkbox"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $employee->exists ? $employee->is_active : true))><span>Đang làm việc</span></label>
                </div>
                @error('is_active')<small>{{ $message }}</small>@enderror
            </fieldset>

            <div class="admin-form-actions admin-form-wide">
                <a href="{{ route('admin.employees.index') }}">Hủy</a>
                <x-admin.submit-button :label="$employee->exists ? 'Lưu thay đổi' : 'Tạo tài khoản'" />
            </div>
        </form>
    </section>

    @if ($employee->exists)
        @include('admin.employees.partials.assignments')
    @endif
</x-layouts.admin>
