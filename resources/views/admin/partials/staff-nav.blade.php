<nav class="admin-module-nav" aria-label="Khu vực nhân sự và lương">
    @can('viewAny', App\Models\User::class)
        <a href="{{ route('admin.employees.index') }}" @class(['is-active' => request()->routeIs('admin.employees.*')])>Nhân sự</a>
    @endcan
    @can('viewAny', App\Models\AttendanceRecord::class)
        <a href="{{ route('admin.attendance.index') }}" @class(['is-active' => request()->routeIs('admin.attendance.*')])>Chấm công</a>
    @endcan
    <a href="{{ route('admin.payrolls.index') }}" @class(['is-active' => request()->routeIs('admin.payrolls.*')])>Bảng lương</a>
</nav>
