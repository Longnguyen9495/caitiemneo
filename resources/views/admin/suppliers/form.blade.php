<x-layouts.admin :title="$supplier->exists ? 'Sửa nhà cung cấp' : 'Thêm nhà cung cấp'" heading="Kho vật tư">
    @include('admin.partials.inventory-nav')

    <section class="admin-form-panel">
        <x-admin.page-header
            :title="$supplier->exists ? $supplier->name : 'Thêm nhà cung cấp'"
            description="Thông tin này hiển thị trên phiếu nhập kho."
            :breadcrumbs="['Nhà cung cấp' => route('admin.suppliers.index'), ($supplier->exists ? 'Chỉnh sửa' : 'Thêm mới') => null]"
        />

        <form method="POST" action="{{ $supplier->exists ? route('admin.suppliers.update', $supplier) : route('admin.suppliers.store') }}" class="admin-form">
            @csrf
            @if ($supplier->exists)
                @method('PUT')
            @endif

            <label>
                Tên nhà cung cấp
                <input name="name" value="{{ old('name', $supplier->name) }}" required autofocus>
                @error('name')<small>{{ $message }}</small>@enderror
            </label>

            <label>
                Số điện thoại
                <input name="phone" value="{{ old('phone', $supplier->phone) }}" inputmode="tel">
                @error('phone')<small>{{ $message }}</small>@enderror
            </label>

            <label>
                Email
                <input type="email" name="email" value="{{ old('email', $supplier->email) }}">
                @error('email')<small>{{ $message }}</small>@enderror
            </label>

            <label class="admin-form-wide">
                Địa chỉ
                <textarea name="address" rows="2">{{ old('address', $supplier->address) }}</textarea>
                @error('address')<small>{{ $message }}</small>@enderror
            </label>

            <label class="admin-checkbox admin-form-wide">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $supplier->exists ? $supplier->is_active : true))>
                <span>Đang hợp tác</span>
            </label>

            <div class="admin-form-actions admin-form-wide">
                <a href="{{ route('admin.suppliers.index') }}">Hủy</a>
                <x-admin.submit-button :label="$supplier->exists ? 'Lưu thay đổi' : 'Thêm nhà cung cấp'" />
            </div>
        </form>
    </section>
</x-layouts.admin>
