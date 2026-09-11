<x-layouts.admin :title="$shift->exists ? 'Sửa ca làm' : 'Thêm ca làm'" heading="Danh mục ca">
    <x-admin.page-header
        :title="$shift->exists ? $shift->name : 'Ca làm mới'"
        description="Sửa ca ở đây không làm thay đổi những ca đã phân trước đó: lịch phân ca giữ bản sao giờ dự kiến của riêng nó."
        :breadcrumbs="['Danh mục ca' => route('admin.work-shifts.index'), ($shift->exists ? 'Chỉnh sửa' : 'Thêm mới') => null]"
    />

    <form method="POST" class="card p-3 p-lg-4"
          action="{{ $shift->exists ? route('admin.work-shifts.update', $shift) : route('admin.work-shifts.store') }}">
        @csrf
        @if ($shift->exists)
            @method('PUT')
        @endif

        <div class="row g-3">
            <x-admin.field name="name" label="Tên ca" required>
                <input class="form-control @error('name') is-invalid @enderror" id="name" name="name"
                       value="{{ old('name', $shift->name) }}" required autofocus placeholder="Ca sáng">
            </x-admin.field>

            <x-admin.field name="branch_id" label="Phạm vi áp dụng"
                           help="Để trống nếu mọi chi nhánh đều dùng chung khung giờ này.">
                <select class="form-select @error('branch_id') is-invalid @enderror" id="branch_id" name="branch_id"
                        @disabled(! auth()->user()->isOwner() && ! $shift->exists)>
                    @if (auth()->user()->isOwner())
                        <option value="">Dùng chung mọi chi nhánh</option>
                    @endif
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected((string) old('branch_id', $shift->branch_id) === (string) $branch->id)>
                            {{ $branch->name }}
                        </option>
                    @endforeach
                </select>
            </x-admin.field>

            <x-admin.field name="starts_at" label="Giờ bắt đầu" col="col-6 col-lg-3" required>
                <input class="form-control neo-num @error('starts_at') is-invalid @enderror" id="starts_at" name="starts_at"
                       type="time" required
                       value="{{ old('starts_at', $shift->exists ? $shift->formatTime('starts_at') : '09:00') }}">
            </x-admin.field>

            <x-admin.field name="ends_at" label="Giờ kết thúc" col="col-6 col-lg-3" required
                           help="Nhỏ hơn giờ bắt đầu nghĩa là ca qua đêm.">
                <input class="form-control neo-num @error('ends_at') is-invalid @enderror" id="ends_at" name="ends_at"
                       type="time" required
                       value="{{ old('ends_at', $shift->exists ? $shift->formatTime('ends_at') : '19:00') }}">
            </x-admin.field>

            <x-admin.field name="shift_value" label="Hệ số ca" col="col-6 col-lg-3" required
                           help="1 là một ca công đầy đủ.">
                <input class="form-control neo-num @error('shift_value') is-invalid @enderror" id="shift_value" name="shift_value"
                       type="number" step="0.25" min="0" max="10" required
                       value="{{ old('shift_value', $shift->shift_value ?? 1) }}">
            </x-admin.field>

            <x-admin.field name="grace_minutes" label="Ân hạn (phút)" col="col-6 col-lg-3" required
                           help="Đến muộn trong khoảng này vẫn tính là đi làm đúng giờ.">
                <input class="form-control neo-num @error('grace_minutes') is-invalid @enderror" id="grace_minutes" name="grace_minutes"
                       type="number" step="1" min="0" max="120" required
                       value="{{ old('grace_minutes', $shift->grace_minutes ?? 0) }}">
            </x-admin.field>

            <x-admin.field name="early_check_in_minutes" label="Cho vào ca sớm (phút)" col="col-6 col-lg-3"
                           help="Để trống thì dùng mức chung {{ config('attendance.early_check_in_minutes') }} phút.">
                <input class="form-control neo-num @error('early_check_in_minutes') is-invalid @enderror"
                       id="early_check_in_minutes" name="early_check_in_minutes"
                       type="number" step="1" min="0" max="240"
                       value="{{ old('early_check_in_minutes', $shift->early_check_in_minutes) }}">
            </x-admin.field>

            <div class="col-12">
                <label class="form-check card px-3 py-2 mb-0 d-flex flex-row align-items-center gap-2">
                    <input class="form-check-input m-0 flex-shrink-0" type="checkbox" name="is_active" value="1"
                           @checked(old('is_active', $shift->exists ? $shift->is_active : true))>
                    <span>Đang sử dụng</span>
                </label>
            </div>
        </div>

        <div class="neo-formbar">
            <a href="{{ route('admin.work-shifts.index') }}" class="btn btn-light">Hủy</a>
            <x-admin.submit-button :label="$shift->exists ? 'Lưu thay đổi' : 'Thêm ca'" />
        </div>
    </form>
</x-layouts.admin>
