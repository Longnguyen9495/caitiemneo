<x-layouts.admin :title="$employee->exists ? 'Sửa nhân sự' : 'Thêm nhân sự'" heading="Nhân sự">
    @include('admin.partials.staff-nav')

    <x-admin.page-header
        :title="$employee->exists ? $employee->name : 'Tài khoản nhân sự mới'"
        :description="$employee->exists ? 'Để trống mật khẩu nếu không muốn thay đổi.' : 'Tài khoản mới đăng nhập bằng email và mật khẩu bên dưới.'"
        :breadcrumbs="['Nhân sự' => route('admin.employees.index'), ($employee->exists ? 'Chỉnh sửa' : 'Thêm mới') => null]"
    />

    <form method="POST" class="card p-3 p-lg-4 mb-3"
          action="{{ $employee->exists ? route('admin.employees.update', $employee) : route('admin.employees.store') }}">
        @csrf
        @if ($employee->exists)
            @method('PUT')
        @endif

        <div class="row g-3">
            <x-admin.field name="name" label="Họ tên" required>
                <input class="form-control @error('name') is-invalid @enderror" id="name" name="name"
                       value="{{ old('name', $employee->name) }}" required autofocus>
            </x-admin.field>

            <x-admin.field name="username" label="Tên đăng nhập">
                <input class="form-control @error('username') is-invalid @enderror" id="username" name="username"
                       value="{{ old('username', $employee->username) }}" placeholder="Không bắt buộc">
            </x-admin.field>

            <x-admin.field name="email" label="Email" required>
                <input class="form-control @error('email') is-invalid @enderror" id="email" name="email" type="email"
                       value="{{ old('email', $employee->email) }}" required>
            </x-admin.field>

            <x-admin.field name="phone" label="Số điện thoại">
                <input class="form-control neo-num @error('phone') is-invalid @enderror" id="phone" name="phone" type="tel"
                       value="{{ old('phone', $employee->phone) }}">
            </x-admin.field>

            <x-admin.field name="password" :label="$employee->exists ? 'Mật khẩu (để trống nếu giữ nguyên)' : 'Mật khẩu'" :required="! $employee->exists">
                <input class="form-control @error('password') is-invalid @enderror" id="password" name="password"
                       type="password" autocomplete="new-password" @required(! $employee->exists)>
            </x-admin.field>

            <x-admin.field name="password_confirmation" label="Nhập lại mật khẩu" :required="! $employee->exists">
                <input class="form-control" id="password_confirmation" name="password_confirmation"
                       type="password" autocomplete="new-password" @required(! $employee->exists)>
            </x-admin.field>

            <x-admin.field name="role" label="Vai trò" required>
                <select class="form-select @error('role') is-invalid @enderror" id="role" name="role" required>
                    @foreach ($roles as $value => $label)
                        <option value="{{ $value }}" @selected(old('role', $employee->role?->value) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </x-admin.field>

            @unless ($employee->exists)
                {{-- Một tài khoản chưa có phân công thì không vào được khu vực quản trị,
                     nên chi nhánh đầu tiên được chọn ngay khi tạo. --}}
                <x-admin.field name="branch_id" label="Chi nhánh làm việc" required>
                    <select class="form-select @error('branch_id') is-invalid @enderror" id="branch_id" name="branch_id" required>
                        @if ($defaultBranchId === null)
                            <option value="" selected disabled>Chọn chi nhánh</option>
                        @endif
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected((string) old('branch_id', $defaultBranchId) === (string) $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </x-admin.field>
            @endunless

            <x-admin.field name="base_salary" label="Lương cứng theo kỳ" required>
                <x-admin.money-input name="base_salary" :value="$employee->base_salary ?? 0" required />
            </x-admin.field>

            <x-admin.field name="shift_rate" label="Đơn giá mỗi ca" required>
                <x-admin.money-input name="shift_rate" :value="$employee->shift_rate ?? 0" required />
            </x-admin.field>

            <x-admin.field name="commission_rate" label="Tỷ lệ hoa hồng (%)" required>
                <input class="form-control text-end neo-num @error('commission_rate') is-invalid @enderror" id="commission_rate"
                       type="number" step="0.5" min="0" max="100" name="commission_rate"
                       value="{{ old('commission_rate', $employee->commission_rate ?? 0) }}" required>
            </x-admin.field>

            <div class="col-12">
                <fieldset>
                    <legend class="form-label">Quyền thao tác</legend>
                    <div class="row g-2">
                        @foreach ([
                            'can_manage_appointments' => 'Quản lý lịch hẹn',
                            'can_create_invoices' => 'Tạo và thanh toán hóa đơn',
                            'can_manage_payroll' => 'Quản lý bảng lương (dành cho quản lý)',
                            'is_active' => 'Đang làm việc',
                        ] as $field => $label)
                            <div class="col-12 col-lg-6">
                                <label class="form-check card px-3 py-2 mb-0 d-flex flex-row align-items-center gap-2">
                                    <input class="form-check-input m-0 flex-shrink-0" type="checkbox" name="{{ $field }}" value="1"
                                           @checked(old($field, $field === 'is_active' ? ($employee->exists ? $employee->is_active : true) : $employee->{$field}))>
                                    <span>{{ $label }}</span>
                                </label>
                            </div>
                        @endforeach
                    </div>
                    @error('is_active')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </fieldset>
            </div>
        </div>

        <div class="neo-formbar">
            <a href="{{ route('admin.employees.index') }}" class="btn btn-light">Hủy</a>
            <x-admin.submit-button :label="$employee->exists ? 'Lưu thay đổi' : 'Tạo tài khoản'" />
        </div>
    </form>

    @if ($employee->exists)
        @include('admin.employees.partials.assignments')
    @endif
</x-layouts.admin>
