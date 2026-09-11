<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <h2>Phân công chi nhánh</h2>
            <p>Lịch sử phân công không bị xóa. Muốn dừng, hãy đặt ngày kết thúc để giữ lại dấu vết.</p>
        </div>
    </header>

    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>Chi nhánh</th><th>Vai trò</th><th>Từ ngày</th><th>Đến ngày</th><th></th></tr></thead>
            <tbody>
                @forelse ($employee->branchAssignments->sortByDesc('starts_on') as $assignment)
                    <tr>
                        <td><span class="admin-branch-chip">{{ $assignment->branch?->code }}</span> <strong>{{ $assignment->branch?->name }}</strong></td>
                        <td>
                            <x-admin.status-badge
                                :tone="$assignment->is_primary ? 'is-active' : 'is-muted'"
                                :label="$assignment->is_primary ? 'Chi nhánh chính' : 'Kiêm nhiệm'"
                            />
                        </td>
                        <td>{{ $assignment->starts_on->format('d/m/Y') }}</td>
                        <td>{{ $assignment->ends_on?->format('d/m/Y') ?? 'Đang hiệu lực' }}</td>
                        <td>
                            @if ($assignment->ends_on === null)
                                <form method="POST" action="{{ route('admin.employees.assignments.update', [$employee, $assignment]) }}" class="admin-inline-form">
                                    @csrf
                                    @method('PATCH')
                                    <input type="date" name="ends_on" value="{{ now()->toDateString() }}" required>
                                    <button type="submit" class="admin-button is-ghost">Kết thúc</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <x-admin.empty-state :colspan="5" title="Nhân viên chưa được phân công chi nhánh nào" hint="Tài khoản chưa có phân công sẽ không vào được khu vực quản trị." />
                @endforelse
            </tbody>
        </table>
    </div>

    <form method="POST" action="{{ route('admin.employees.assignments.store', $employee) }}" class="admin-form" style="padding: 1.25rem 1.4rem;">
        @csrf

        <label>
            Chi nhánh
            <select name="branch_id" required>
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}" @selected((string) old('branch_id') === (string) $branch->id)>{{ $branch->name }}</option>
                @endforeach
            </select>
            @error('branch_id')<small>{{ $message }}</small>@enderror
        </label>

        <label>
            Từ ngày
            <input type="date" name="starts_on" value="{{ old('starts_on', now()->toDateString()) }}" required>
            @error('starts_on')<small>{{ $message }}</small>@enderror
        </label>

        <label>
            Đến ngày
            <input type="date" name="ends_on" value="{{ old('ends_on') }}">
            @error('ends_on')<small>{{ $message }}</small>@enderror
        </label>

        <label class="admin-checkbox">
            <input type="checkbox" name="is_primary" value="1" @checked(old('is_primary', true))>
            <span>Là chi nhánh chính</span>
        </label>

        <div class="admin-form-actions admin-form-wide">
            <x-admin.submit-button label="Thêm phân công" variant="ghost" />
        </div>
    </form>
</section>
