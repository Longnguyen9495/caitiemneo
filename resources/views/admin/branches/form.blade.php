<x-layouts.admin :title="$branch->exists ? 'Sửa chi nhánh' : 'Thêm chi nhánh'" heading="Chi nhánh">
    <x-admin.page-header
        :title="$branch->exists ? $branch->name : 'Chi nhánh mới'"
        description="Mã chi nhánh được dùng trong số hóa đơn và phiếu chuyển kho nên không nên đổi sau khi đã phát sinh dữ liệu."
        :breadcrumbs="['Chi nhánh' => route('admin.branches.index'), ($branch->exists ? 'Chỉnh sửa' : 'Thêm mới') => null]"
    />

    <form method="POST" class="card p-3 p-lg-4"
          action="{{ $branch->exists ? route('admin.branches.update', $branch) : route('admin.branches.store') }}">
        @csrf
        @if ($branch->exists)
            @method('PUT')
        @endif

        <div class="row g-3">
            <x-admin.field name="code" label="Mã chi nhánh" required>
                <input class="form-control @error('code') is-invalid @enderror" id="code" name="code"
                       value="{{ old('code', $branch->code) }}" required autofocus placeholder="CN-03">
            </x-admin.field>

            <x-admin.field name="name" label="Tên chi nhánh" required>
                <input class="form-control @error('name') is-invalid @enderror" id="name" name="name"
                       value="{{ old('name', $branch->name) }}" required>
            </x-admin.field>

            <x-admin.field name="phone" label="Số điện thoại">
                <input class="form-control neo-num @error('phone') is-invalid @enderror" id="phone" name="phone" type="tel"
                       value="{{ old('phone', $branch->phone) }}">
            </x-admin.field>

            <x-admin.field name="address" label="Địa chỉ">
                <input class="form-control @error('address') is-invalid @enderror" id="address" name="address"
                       value="{{ old('address', $branch->address) }}">
            </x-admin.field>

            <div class="col-12">
                <hr class="my-2">
                <h3 class="fs-6 fw-semibold mb-1">Chấm công GPS</h3>
                <p class="small text-body-secondary mb-0">
                    Toạ độ cửa hàng là tâm của vùng cho phép chấm công. Lấy nhanh bằng cách mở Google Maps,
                    bấm giữ vào vị trí tiệm rồi sao chép cặp số hiện ra.
                </p>
            </div>

            <x-admin.field name="latitude" label="Vĩ độ" col="col-6 col-lg-3"
                           help="Ví dụ 10.7769000">
                <input class="form-control neo-num @error('latitude') is-invalid @enderror" id="latitude" name="latitude"
                       type="text" inputmode="decimal" value="{{ old('latitude', $branch->latitude) }}">
            </x-admin.field>

            <x-admin.field name="longitude" label="Kinh độ" col="col-6 col-lg-3"
                           help="Ví dụ 106.7009000">
                <input class="form-control neo-num @error('longitude') is-invalid @enderror" id="longitude" name="longitude"
                       type="text" inputmode="decimal" value="{{ old('longitude', $branch->longitude) }}">
            </x-admin.field>

            <x-admin.field name="attendance_radius_meters" label="Bán kính cho phép (m)" col="col-6 col-lg-3" required
                           help="Mặc định 100. Khu vực sóng yếu có thể nới lên 150–200.">
                <input class="form-control neo-num @error('attendance_radius_meters') is-invalid @enderror"
                       id="attendance_radius_meters" name="attendance_radius_meters"
                       type="number" step="1" min="{{ config('attendance.min_radius_meters') }}" max="{{ config('attendance.max_radius_meters') }}" required
                       value="{{ old('attendance_radius_meters', $branch->attendance_radius_meters ?? config('attendance.default_radius_meters')) }}">
            </x-admin.field>

            <x-admin.field name="attendance_accuracy_limit_meters" label="Ngưỡng sai số GPS (m)" col="col-6 col-lg-3" required
                           help="Từ chối chấm công khi máy báo sai số lớn hơn mức này. Mặc định 150.">
                <input class="form-control neo-num @error('attendance_accuracy_limit_meters') is-invalid @enderror"
                       id="attendance_accuracy_limit_meters" name="attendance_accuracy_limit_meters"
                       type="number" step="1" min="{{ config('attendance.min_radius_meters') }}" max="{{ config('attendance.max_radius_meters') }}" required
                       value="{{ old('attendance_accuracy_limit_meters', $branch->attendance_accuracy_limit_meters ?? config('attendance.default_accuracy_limit_meters')) }}">
            </x-admin.field>

            <div class="col-12">
                <label class="form-check card px-3 py-2 mb-0 d-flex flex-row align-items-center gap-2">
                    <input class="form-check-input m-0 flex-shrink-0" type="checkbox" name="gps_attendance_enabled" value="1"
                           @checked(old('gps_attendance_enabled', $branch->gps_attendance_enabled))>
                    <span>Cho phép nhân viên tự chấm công bằng GPS tại chi nhánh này</span>
                </label>
            </div>

            <div class="col-12">
                <label class="form-check card px-3 py-2 mb-0 d-flex flex-row align-items-center gap-2">
                    <input class="form-check-input m-0 flex-shrink-0" type="checkbox" name="is_active" value="1"
                           @checked(old('is_active', $branch->exists ? $branch->is_active : true))>
                    <span>Đang hoạt động</span>
                </label>
            </div>
        </div>

        <div class="neo-formbar">
            <a href="{{ route('admin.branches.index') }}" class="btn btn-light">Hủy</a>
            <x-admin.submit-button :label="$branch->exists ? 'Lưu thay đổi' : 'Thêm chi nhánh'" />
        </div>
    </form>
</x-layouts.admin>
