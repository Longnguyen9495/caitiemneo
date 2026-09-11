<nav class="neo-chips mb-3" aria-label="Khu vực nhân sự và lương">
    @can('viewAny', App\Models\AttendanceRecord::class)
        <a href="{{ route('admin.attendance.index') }}" @class(['neo-chip', 'is-active' => request()->routeIs('admin.attendance.index') || request()->routeIs('admin.attendance.edit')])>Chấm công</a>
        <a href="{{ route('admin.attendance.review') }}" @class(['neo-chip', 'is-active' => request()->routeIs('admin.attendance.review')])>Cần xem lại</a>
    @endcan
    @can('viewAny', App\Models\ShiftAssignment::class)
        <a href="{{ route('admin.shift-schedule.index') }}" @class(['neo-chip', 'is-active' => request()->routeIs('admin.shift-schedule.*')])>Lịch ca</a>
    @endcan
    @can('viewAny', App\Models\WorkShift::class)
        <a href="{{ route('admin.work-shifts.index') }}" @class(['neo-chip', 'is-active' => request()->routeIs('admin.work-shifts.*')])>Danh mục ca</a>
    @endcan
    <a href="{{ route('admin.payrolls.index') }}" @class(['neo-chip', 'is-active' => request()->routeIs('admin.payrolls.*')])>Bảng lương</a>
    @can('viewAny', App\Models\User::class)
        <a href="{{ route('admin.employees.index') }}" @class(['neo-chip', 'is-active' => request()->routeIs('admin.employees.*')])>Nhân sự</a>
    @endcan
</nav>
