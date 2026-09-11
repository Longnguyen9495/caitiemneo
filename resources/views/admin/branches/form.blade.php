<x-layouts.admin :title="$branch->exists ? 'Sửa chi nhánh' : 'Thêm chi nhánh'" heading="Chi nhánh">
    <section class="admin-form-panel">
        <x-admin.page-header
            :title="$branch->exists ? $branch->name : 'Thêm chi nhánh mới'"
            description="Mã chi nhánh được dùng trong số hóa đơn và phiếu chuyển kho nên không nên đổi sau khi đã phát sinh dữ liệu."
            :breadcrumbs="['Chi nhánh' => route('admin.branches.index'), ($branch->exists ? 'Chỉnh sửa' : 'Thêm mới') => null]"
        />

        <form method="POST" action="{{ $branch->exists ? route('admin.branches.update', $branch) : route('admin.branches.store') }}" class="admin-form">
            @csrf
            @if ($branch->exists)
                @method('PUT')
            @endif

            <label>
                Mã chi nhánh
                <input name="code" value="{{ old('code', $branch->code) }}" required autofocus placeholder="CN-03">
                @error('code')<small>{{ $message }}</small>@enderror
            </label>

            <label>
                Tên chi nhánh
                <input name="name" value="{{ old('name', $branch->name) }}" required>
                @error('name')<small>{{ $message }}</small>@enderror
            </label>

            <label>
                Số điện thoại
                <input name="phone" value="{{ old('phone', $branch->phone) }}" inputmode="tel">
                @error('phone')<small>{{ $message }}</small>@enderror
            </label>

            <label>
                Địa chỉ
                <input name="address" value="{{ old('address', $branch->address) }}">
                @error('address')<small>{{ $message }}</small>@enderror
            </label>

            <label class="admin-checkbox admin-form-wide">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $branch->exists ? $branch->is_active : true))>
                <span>Đang hoạt động</span>
            </label>

            <div class="admin-form-actions admin-form-wide">
                <a href="{{ route('admin.branches.index') }}">Hủy</a>
                <x-admin.submit-button :label="$branch->exists ? 'Lưu thay đổi' : 'Thêm chi nhánh'" />
            </div>
        </form>
    </section>
</x-layouts.admin>
