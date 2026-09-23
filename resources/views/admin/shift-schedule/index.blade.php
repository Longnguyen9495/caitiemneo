<x-layouts.admin title="Lịch phân ca" heading="Lịch phân ca">
    @include('admin.partials.staff-nav')

    <x-admin.page-header
        :title="'Tuần '.$weekStart->format('d/m').' – '.$weekEnd->format('d/m/Y')"
        :description="$managesRoster
            ? 'Phân ca trước cho cả tuần để nhân viên biết giờ vào và hệ thống biết khi nào là đi trễ.'
            : 'Đây là lịch làm việc của bạn trong tuần.'"
    >
        <x-slot:actions>
            <div class="btn-group btn-group-sm" role="group" aria-label="Chuyển tuần">
                <a class="btn btn-outline-secondary" href="{{ route('admin.shift-schedule.index', ['week' => $weekStart->copy()->subWeek()->toDateString()]) }}">Tuần trước</a>
                <a class="btn btn-outline-secondary" href="{{ route('admin.shift-schedule.index') }}">Tuần này</a>
                <a class="btn btn-outline-secondary" href="{{ route('admin.shift-schedule.index', ['week' => $weekStart->copy()->addWeek()->toDateString()]) }}">Tuần sau</a>
            </div>
        </x-slot:actions>
    </x-admin.page-header>

    @if ($managesRoster)
        <section class="card p-3 mb-3">
            <h2 class="fs-6 fw-semibold mb-3">Phân ca nhanh</h2>

            <form method="POST" action="{{ route('admin.shift-schedule.store') }}" class="row g-2 align-items-end">
                @csrf

                <x-admin.select-field name="employee_id" label="Nhân viên" col="col-12 col-lg-3" placeholder="Chọn nhân viên" required>
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->id }}" @selected((string) old('employee_id') === (string) $employee->id)>{{ $employee->name }}</option>
                    @endforeach
                </x-admin.select-field>

                <x-admin.select-field name="work_shift_id" label="Ca làm" col="col-12 col-lg-3" placeholder="Chọn ca" required>
                    @foreach ($shifts as $shift)
                        <option value="{{ $shift->id }}" @selected((string) old('work_shift_id') === (string) $shift->id)>{{ $shift->label() }}</option>
                    @endforeach
                </x-admin.select-field>

                <x-admin.field name="work_date" label="Ngày làm" col="col-6 col-lg-2" required>
                    <input class="form-control neo-num @error('work_date') is-invalid @enderror" id="work_date" name="work_date"
                           type="date" required value="{{ old('work_date', $defaultDate) }}">
                </x-admin.field>

                <x-admin.field name="note" label="Ghi chú" col="col-6 col-lg-2">
                    <input class="form-control @error('note') is-invalid @enderror" id="note" name="note" value="{{ old('note') }}">
                </x-admin.field>

                <div class="col-12 col-lg-2">
                    <x-admin.submit-button label="Phân ca" class="w-100" />
                </div>
            </form>

            @if ($shifts->isEmpty())
                <p class="form-text mb-0 mt-2">
                    Chưa có ca nào trong danh mục.
                    <a href="{{ route('admin.work-shifts.create') }}">Tạo ca đầu tiên</a> trước khi phân lịch.
                </p>
            @endif
        </section>
    @endif

    <section class="card overflow-hidden">
        <table class="table neo-table neo-table--roster align-middle mb-0">
            <caption class="visually-hidden">Lịch phân ca</caption>
            <thead>
                <tr>
                    <th scope="col">Nhân viên</th>
                    @foreach ($days as $day)
                        <th scope="col" class="text-center">
                            <span class="d-block small">{{ $day->translatedFormat('D') }}</span>
                            <span class="neo-num">{{ $day->format('d/m') }}</span>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td class="fw-semibold">{{ $row['employee']?->name }}</td>
                        @foreach ($days as $day)
                            @php $cell = $row['days'][$day->toDateString()] ?? null; @endphp
                            <td data-label="{{ $day->translatedFormat('D d/m') }}" @class(['text-lg-center', 'is-empty' => blank($cell)])>
                                @forelse ($cell ?? [] as $assignment)
                                    <div class="d-inline-flex flex-wrap align-items-center gap-1 mb-1">
                                        <span class="badge rounded-pill text-bg-light border neo-num">
                                            {{ $assignment->planned_start_at->format('H:i') }}–{{ $assignment->planned_end_at->format('H:i') }}
                                        </span>
                                        @if ($assignment->attendanceRecord)
                                            <x-admin.status-badge :status="$assignment->attendanceRecord->status" />
                                        @elseif ($managesRoster)
                                            <x-admin.confirm-form
                                                :action="route('admin.shift-schedule.destroy', $assignment)"
                                                method="DELETE"
                                                label="Bỏ"
                                                :message="'Bỏ phân ca '.$assignment->shift_name.' ngày '.$assignment->work_date->format('d/m').'?'"
                                            />
                                        @endif
                                    </div>
                                @empty
                                    <span class="text-body-secondary">—</span>
                                @endforelse
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <x-admin.empty-state
                        :colspan="8"
                        icon="calendar"
                        title="Tuần này chưa phân ca cho ai"
                        :hint="$managesRoster ? 'Dùng biểu mẫu Phân ca nhanh ở trên để thêm ca đầu tiên.' : 'Quản lý sẽ phân ca cho bạn trước khi tuần bắt đầu.'"
                    />
                @endforelse
            </tbody>
        </table>
    </section>
</x-layouts.admin>
