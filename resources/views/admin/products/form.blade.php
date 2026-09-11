<x-layouts.admin :title="$product->exists ? 'Sửa vật tư' : 'Thêm vật tư'" heading="Kho vật tư">
    @include('admin.partials.inventory-nav')

    <section class="admin-form-panel">
        <x-admin.page-header
            :title="$product->exists ? $product->name : 'Thêm vật tư mới'"
            description="Vật tư đã phát sinh phiếu kho không bị xóa; hãy chuyển sang trạng thái ngưng dùng."
            :breadcrumbs="['Vật tư' => route('admin.products.index'), ($product->exists ? 'Chỉnh sửa' : 'Thêm mới') => null]"
        />

        <form method="POST" action="{{ $product->exists ? route('admin.products.update', $product) : route('admin.products.store') }}" class="admin-form">
            @csrf
            @if ($product->exists)
                @method('PUT')
            @endif

            <label>
                Tên vật tư
                <input name="name" value="{{ old('name', $product->name) }}" required autofocus>
                @error('name')<small>{{ $message }}</small>@enderror
            </label>

            <label>
                Mã SKU
                <input name="sku" value="{{ old('sku', $product->sku) }}" placeholder="Không bắt buộc">
                @error('sku')<small>{{ $message }}</small>@enderror
            </label>

            <label>
                Đơn vị tính
                <input name="unit" value="{{ old('unit', $product->unit ?: 'đơn vị') }}" required>
                @error('unit')<small>{{ $message }}</small>@enderror
            </label>

            <label>
                Giá vốn (VNĐ)
                <x-admin.money-input name="cost_price" :value="$product->cost_price" required />
                @error('cost_price')<small>{{ $message }}</small>@enderror
            </label>

            <label>
                Định mức tồn tối thiểu
                <input class="admin-money-input" type="number" step="0.01" min="0" name="minimum_stock" value="{{ old('minimum_stock', $product->minimum_stock ?? 0) }}" required>
                @error('minimum_stock')<small>{{ $message }}</small>@enderror
            </label>

            <label class="admin-checkbox admin-form-wide">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product->exists ? $product->is_active : true))>
                <span>Đang sử dụng trong tiệm</span>
            </label>

            <div class="admin-form-actions admin-form-wide">
                <a href="{{ route('admin.products.index') }}">Hủy</a>
                <x-admin.submit-button :label="$product->exists ? 'Lưu thay đổi' : 'Thêm vật tư'" />
            </div>
        </form>
    </section>
</x-layouts.admin>
