<section class="card">
    <div class="card-header">
        <h2 class="neo-display fs-5 mb-0">Phân công chi nhánh</h2>
        <p class="mb-0 small text-body-secondary">Lịch sử phân công không bị xóa. Muốn dừng, hãy đặt ngày kết thúc để giữ lại dấu vết.</p>
    </div>

    <table class="table neo-table align-middle mb-0">
        <thead>
            <tr>
                <th scope="col">Chi nhánh</th>
                <th scope="col">Vai trò</th>
                <th scope="col">Từ ngày</th>
                <th scope="col">Đến ngày</th>
                <th scope="col"><span class="visually-hidden">Thao tác</span></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($employee->branchAssignments->sortByDesc('starts_on') as $assignment)
                <tr>
                    <td>
                        <span class="badge rounded-pill text-bg-light border fw-normal me-1">{{ $assignment->branch?->code }}</span>
                        <span class="fw-semibold">{{ $assignment->branch?->name }}</span>
                    </td>
                    <td data-label="Vai trò">
                        <x-admin.status-badge
                            :tone="$assignment->is_primary ? 'is-active' : 'is-muted'"
                            :label="$assignment->is_primary ? 'Chi nhánh chính' : 'Kiêm nhiệm'"
                        />
                    </td>
                    <td data-label="Từ ngày" class="neo-num">{{ $assignment->starts_on->format('d/m/Y') }}</td>
                    <td data-label="Đến ngày" class="neo-num">{{ $assignment->ends_on?->format('d/m/Y') ?? 'Đang hiệu lực' }}</td>
                    <td>
                        @if ($assignment->ends_on === null)
                            <form method="POST" action="{{ route('admin.employees.assignments.update', [$employee, $assignment]) }}"
                                  class="d-flex gap-2 align-items-center">
                                @csrf
                                @method('PATCH')
                                <label class="visually-hidden" for="ends_on-{{ $assignment->id }}">Ngày kết thúc</label>
                                <input class="form-control form-control-sm" id="ends_on-{{ $assignment->id }}" type="date"
                                       name="ends_on" value="{{ now()->toDateString() }}" required style="max-width:10rem">
                                <button type="submit" class="btn btn-sm btn-outline-secondary text-nowrap">Kết thúc</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <x-admin.empty-state
                    :colspan="5"
                    icon="branch"
                    title="Nhân viên chưa được phân công chi nhánh nào"
                    hint="Tài khoản chưa có phân công sẽ không vào được khu vực quản trị."
                />
            @endforelse
        </tbody>
    </table>

    @can('assignBranch', $employee)
        <form method="POST" action="{{ route('admin.employees.assignments.store', $employee) }}" class="card-body border-top">
            @csrf
            <div class="row g-3">
                <x-admin.field name="branch_id" label="Chi nhánh" required>
                    <select class="form-select @error('branch_id') is-invalid @enderror" id="branch_id" name="branch_id" required>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected((string) old('branch_id') === (string) $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </x-admin.field>

                <x-admin.field name="starts_on" label="Từ ngày" col="col-6 col-lg-3" required>
                    <input class="form-control @error('starts_on') is-invalid @enderror" id="starts_on" type="date"
                           name="starts_on" value="{{ old('starts_on', now()->toDateString()) }}" required>
                </x-admin.field>

                <x-admin.field name="ends_on" label="Đến ngày" col="col-6 col-lg-3">
                    <input class="form-control @error('ends_on') is-invalid @enderror" id="ends_on" type="date"
                           name="ends_on" value="{{ old('ends_on') }}">
                </x-admin.field>

                <div class="col-12">
                    <label class="form-check d-inline-flex align-items-center gap-2 mb-0">
                        <input class="form-check-input m-0" type="checkbox" name="is_primary" value="1" @checked(old('is_primary', true))>
                        <span class="small">Là chi nhánh chính</span>
                    </label>
                </div>
            </div>

            <div class="d-grid d-lg-flex justify-content-lg-end mt-3">
                <x-admin.submit-button label="Thêm phân công" variant="outline-primary" />
            </div>
        </form>
    @endcan
</section>
