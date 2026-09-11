<x-layouts.admin title="Tạo phiếu kho" heading="Kho vật tư">
    @include('admin.partials.inventory-nav')

    <section class="admin-form-panel">
        <x-admin.page-header
            title="Tạo phiếu kho"
            description="Nhập và xuất đều khai số lượng dương. Phiếu điều chỉnh bắt buộc ghi lý do."
            :breadcrumbs="['Nhập xuất kho' => route('admin.inventory.index'), 'Tạo phiếu' => null]"
        />

        <form method="POST" action="{{ route('admin.inventory.store') }}" class="admin-form" x-data="{ type: @js(old('type', $selectedType)) }">
            @csrf

            <label>
                Loại phiếu
                <select name="type" x-model="type" required>
                    @foreach ($types as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('type')<small>{{ $message }}</small>@enderror
            </label>

            <label>
                Vật tư
                <select name="product_id" required>
                    <option value="">Chọn vật tư</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}" @selected((string) old('product_id', $selectedProductId) === (string) $product->id)>
                            {{ $product->name }} — tồn {{ rtrim(rtrim($product->current_stock, '0'), '.') }} {{ $product->unit }}
                        </option>
                    @endforeach
                </select>
                @error('product_id')<small>{{ $message }}</small>@enderror
            </label>

            <label x-show="type === 'adjustment'" x-cloak>
                Kiểu điều chỉnh
                <select name="adjustment_mode">
                    <option value="absolute" @selected(old('adjustment_mode') === 'absolute')>Nhập tồn thực tế đã kiểm đếm</option>
                    <option value="delta" @selected(old('adjustment_mode') === 'delta')>Nhập mức chênh lệch (có thể âm)</option>
                </select>
                @error('adjustment_mode')<small>{{ $message }}</small>@enderror
            </label>

            <label>
                Số lượng
                <input class="admin-money-input" type="number" step="0.01" name="quantity" value="{{ old('quantity') }}" required>
                @error('quantity')<small>{{ $message }}</small>@enderror
            </label>

            <label x-show="type !== 'out'" x-cloak>
                Nhà cung cấp
                <select name="supplier_id">
                    <option value="">Không chọn</option>
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" @selected((string) old('supplier_id') === (string) $supplier->id)>{{ $supplier->name }}</option>
                    @endforeach
                </select>
                @error('supplier_id')<small>{{ $message }}</small>@enderror
            </label>

            <label>
                Đơn giá (VNĐ)
                <x-admin.money-input name="unit_cost" :value="old('unit_cost')" />
                @error('unit_cost')<small>{{ $message }}</small>@enderror
            </label>

            <label>
                Thời điểm
                <input type="datetime-local" name="occurred_at" required value="{{ old('occurred_at', now()->format('Y-m-d\TH:i')) }}">
                @error('occurred_at')<small>{{ $message }}</small>@enderror
            </label>

            <label>
                Tham chiếu
                <input name="reference" value="{{ old('reference') }}" placeholder="Số hóa đơn nhà cung cấp…">
                @error('reference')<small>{{ $message }}</small>@enderror
            </label>

            <label class="admin-form-wide">
                Ghi chú <span x-show="type === 'adjustment'" x-cloak>(bắt buộc với phiếu điều chỉnh)</span>
                <textarea name="note" rows="3">{{ old('note') }}</textarea>
                @error('note')<small>{{ $message }}</small>@enderror
            </label>

            <div class="admin-form-actions admin-form-wide">
                <a href="{{ route('admin.inventory.index') }}">Hủy</a>
                <x-admin.submit-button label="Ghi nhận phiếu kho" />
            </div>
        </form>
    </section>
</x-layouts.admin>
