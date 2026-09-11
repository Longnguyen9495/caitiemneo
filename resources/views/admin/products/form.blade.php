<x-layouts.admin :title="$product->exists ? 'Sửa vật tư' : 'Thêm vật tư'" heading="Kho vật tư">
    @include('admin.partials.inventory-nav')

    <x-admin.page-header
        :title="$product->exists ? $product->name : 'Vật tư mới'"
        description="Vật tư đã phát sinh phiếu kho không bị xóa; hãy chuyển sang trạng thái ngưng dùng."
        :breadcrumbs="['Vật tư' => route('admin.products.index'), ($product->exists ? 'Chỉnh sửa' : 'Thêm mới') => null]"
    />

    <form method="POST" class="card p-3 p-lg-4"
          action="{{ $product->exists ? route('admin.products.update', $product) : route('admin.products.store') }}">
        @csrf
        @if ($product->exists)
            @method('PUT')
        @endif

        <div class="row g-3">
            <x-admin.field name="name" label="Tên vật tư" required>
                <input class="form-control @error('name') is-invalid @enderror" id="name" name="name"
                       value="{{ old('name', $product->name) }}" required autofocus>
            </x-admin.field>

            <x-admin.field name="sku" label="Mã SKU">
                <input class="form-control @error('sku') is-invalid @enderror" id="sku" name="sku"
                       value="{{ old('sku', $product->sku) }}" placeholder="Không bắt buộc">
            </x-admin.field>

            <x-admin.field name="unit" label="Đơn vị tính" required>
                <input class="form-control @error('unit') is-invalid @enderror" id="unit" name="unit"
                       value="{{ old('unit', $product->unit ?: 'đơn vị') }}" required>
            </x-admin.field>

            <x-admin.field name="cost_price" label="Giá vốn" required>
                <x-admin.money-input name="cost_price" :value="$product->cost_price" required />
            </x-admin.field>

            <x-admin.field name="minimum_stock" label="Định mức tồn tối thiểu" required
                           help="Đây là mức chung. Mỗi chi nhánh có thể đặt mức riêng trong mục Chi nhánh.">
                <input class="form-control text-end neo-num @error('minimum_stock') is-invalid @enderror" id="minimum_stock"
                       type="number" step="0.01" min="0" name="minimum_stock"
                       value="{{ old('minimum_stock', $product->minimum_stock ?? 0) }}" required>
            </x-admin.field>

            <div class="col-12">
                <label class="form-check card px-3 py-2 mb-0 d-flex flex-row align-items-center gap-2">
                    <input class="form-check-input m-0 flex-shrink-0" type="checkbox" name="is_active" value="1"
                           @checked(old('is_active', $product->exists ? $product->is_active : true))>
                    <span>Đang sử dụng trong tiệm</span>
                </label>
            </div>
        </div>

        <div class="neo-formbar">
            <a href="{{ route('admin.products.index') }}" class="btn btn-light">Hủy</a>
            <x-admin.submit-button :label="$product->exists ? 'Lưu thay đổi' : 'Thêm vật tư'" />
        </div>
    </form>
</x-layouts.admin>
