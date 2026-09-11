<x-layouts.admin :title="$supplier->exists ? 'Sửa nhà cung cấp' : 'Thêm nhà cung cấp'" heading="Kho vật tư">
    @include('admin.partials.inventory-nav')

    <x-admin.page-header
        :title="$supplier->exists ? $supplier->name : 'Nhà cung cấp mới'"
        description="Thông tin này hiển thị trên phiếu nhập kho."
        :breadcrumbs="['Nhà cung cấp' => route('admin.suppliers.index'), ($supplier->exists ? 'Chỉnh sửa' : 'Thêm mới') => null]"
    />

    <form method="POST" class="card p-3 p-lg-4"
          action="{{ $supplier->exists ? route('admin.suppliers.update', $supplier) : route('admin.suppliers.store') }}">
        @csrf
        @if ($supplier->exists)
            @method('PUT')
        @endif

        <div class="row g-3">
            <x-admin.field name="name" label="Tên nhà cung cấp" required>
                <input class="form-control @error('name') is-invalid @enderror" id="name" name="name"
                       value="{{ old('name', $supplier->name) }}" required autofocus>
            </x-admin.field>

            <x-admin.field name="phone" label="Số điện thoại">
                <input class="form-control neo-num @error('phone') is-invalid @enderror" id="phone" name="phone" type="tel"
                       value="{{ old('phone', $supplier->phone) }}">
            </x-admin.field>

            <x-admin.field name="email" label="Email">
                <input class="form-control @error('email') is-invalid @enderror" id="email" name="email" type="email"
                       value="{{ old('email', $supplier->email) }}">
            </x-admin.field>

            <x-admin.field name="address" label="Địa chỉ" col="col-12">
                <textarea class="form-control @error('address') is-invalid @enderror" id="address" name="address" rows="2">{{ old('address', $supplier->address) }}</textarea>
            </x-admin.field>

            <div class="col-12">
                <label class="form-check card px-3 py-2 mb-0 d-flex flex-row align-items-center gap-2">
                    <input class="form-check-input m-0 flex-shrink-0" type="checkbox" name="is_active" value="1"
                           @checked(old('is_active', $supplier->exists ? $supplier->is_active : true))>
                    <span>Đang hợp tác</span>
                </label>
            </div>
        </div>

        <div class="neo-formbar">
            <a href="{{ route('admin.suppliers.index') }}" class="btn btn-light">Hủy</a>
            <x-admin.submit-button :label="$supplier->exists ? 'Lưu thay đổi' : 'Thêm nhà cung cấp'" />
        </div>
    </form>
</x-layouts.admin>
