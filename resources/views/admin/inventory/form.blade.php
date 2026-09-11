<x-layouts.admin title="Tạo phiếu kho" heading="Kho vật tư">
    @include('admin.partials.inventory-nav')

    <x-admin.page-header
        title="Tạo phiếu kho"
        description="Nhập và xuất đều khai số lượng dương. Phiếu điều chỉnh bắt buộc ghi lý do."
        :breadcrumbs="['Nhập xuất kho' => route('admin.inventory.index'), 'Tạo phiếu' => null]"
    />

    <form method="POST" action="{{ route('admin.inventory.store') }}" class="card p-3 p-lg-4"
          x-data="{ type: @js(old('type', $selectedType)) }">
        @csrf

        <div class="row g-3">
            <x-admin.field name="type" label="Loại phiếu" required>
                <select class="form-select @error('type') is-invalid @enderror" id="type" name="type" x-model="type" required>
                    @foreach ($types as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </x-admin.field>

            <x-admin.field name="product_id" label="Vật tư" required>
                <select class="form-select @error('product_id') is-invalid @enderror" id="product_id" name="product_id" required>
                    <option value="">Chọn vật tư</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}" @selected((string) old('product_id', $selectedProductId) === (string) $product->id)>
                            {{ $product->name }} — tồn {{ rtrim(rtrim($product->current_stock, '0'), '.') }} {{ $product->unit }}
                        </option>
                    @endforeach
                </select>
            </x-admin.field>

            <div class="col-12 col-lg-6" x-show="type === 'adjustment'" x-cloak>
                <label class="form-label" for="adjustment_mode">Kiểu điều chỉnh</label>
                <select class="form-select @error('adjustment_mode') is-invalid @enderror" id="adjustment_mode" name="adjustment_mode">
                    <option value="absolute" @selected(old('adjustment_mode') === 'absolute')>Nhập tồn thực tế đã kiểm đếm</option>
                    <option value="delta" @selected(old('adjustment_mode') === 'delta')>Nhập mức chênh lệch (có thể âm)</option>
                </select>
                @error('adjustment_mode')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>

            <x-admin.field name="quantity" label="Số lượng" required>
                <input class="form-control text-end neo-num @error('quantity') is-invalid @enderror" id="quantity"
                       type="number" step="0.01" name="quantity" value="{{ old('quantity') }}" required>
            </x-admin.field>

            <div class="col-12 col-lg-6" x-show="type !== 'out'" x-cloak>
                <label class="form-label" for="supplier_id">Nhà cung cấp</label>
                <select class="form-select @error('supplier_id') is-invalid @enderror" id="supplier_id" name="supplier_id">
                    <option value="">Không chọn</option>
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" @selected((string) old('supplier_id') === (string) $supplier->id)>{{ $supplier->name }}</option>
                    @endforeach
                </select>
                @error('supplier_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>

            <x-admin.field name="unit_cost" label="Đơn giá">
                <x-admin.money-input name="unit_cost" :value="old('unit_cost')" />
            </x-admin.field>

            <x-admin.field name="occurred_at" label="Thời điểm" required>
                <input class="form-control @error('occurred_at') is-invalid @enderror" id="occurred_at" type="datetime-local"
                       name="occurred_at" value="{{ old('occurred_at', now()->format('Y-m-d\TH:i')) }}" required>
            </x-admin.field>

            <x-admin.field name="reference" label="Tham chiếu">
                <input class="form-control @error('reference') is-invalid @enderror" id="reference" name="reference"
                       value="{{ old('reference') }}" placeholder="Số hóa đơn nhà cung cấp…">
            </x-admin.field>

            <div class="col-12">
                <label class="form-label" for="note">
                    Ghi chú <span x-show="type === 'adjustment'" x-cloak class="text-danger">(bắt buộc với phiếu điều chỉnh)</span>
                </label>
                <textarea class="form-control @error('note') is-invalid @enderror" id="note" name="note" rows="3">{{ old('note') }}</textarea>
                @error('note')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="neo-formbar">
            <a href="{{ route('admin.inventory.index') }}" class="btn btn-light">Hủy</a>
            <x-admin.submit-button label="Ghi nhận phiếu kho" />
        </div>
    </form>
</x-layouts.admin>
