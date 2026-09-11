<x-layouts.admin :title="$service->exists ? 'Sửa dịch vụ' : 'Thêm dịch vụ'" heading="Dịch vụ">
    <x-admin.page-header
        :title="$service->exists ? $service->name : 'Dịch vụ mới'"
        description="Thông tin này được dùng trong lịch hẹn và hóa đơn."
        :breadcrumbs="['Dịch vụ' => route('admin.services.index'), ($service->exists ? 'Chỉnh sửa' : 'Thêm mới') => null]"
    />

    <form method="POST" class="card p-3 p-lg-4"
          action="{{ $service->exists ? route('admin.services.update', $service) : route('admin.services.store') }}">
        @csrf
        @if ($service->exists)
            @method('PUT')
        @endif

        <div class="row g-3">
            <x-admin.field name="name" label="Tên dịch vụ" required>
                <input class="form-control @error('name') is-invalid @enderror" id="name" name="name"
                       value="{{ old('name', $service->name) }}" required autofocus>
            </x-admin.field>

            <x-admin.field name="category" label="Nhóm dịch vụ" col="col-6 col-lg-3" required
                           help="Dùng để gom nhóm khi thợ chọn dịch vụ.">
                <select class="form-select @error('category') is-invalid @enderror" id="category" name="category" required>
                    @foreach (App\Enums\ServiceCategory::options() as $value => $label)
                        <option value="{{ $value }}" @selected(old('category', $service->category?->value ?? 'basic_nail') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </x-admin.field>

            <x-admin.field name="unit" label="Đơn vị tính" col="col-6 col-lg-3" required
                           help="Trang trí thường tính theo ngón, còn lại theo bộ.">
                <select class="form-select @error('unit') is-invalid @enderror" id="unit" name="unit" required>
                    @foreach (App\Enums\ServiceUnit::options() as $value => $label)
                        <option value="{{ $value }}" @selected(old('unit', $service->unit?->value ?? 'set') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </x-admin.field>

            <x-admin.field name="price" label="Giá niêm yết" col="col-12 col-lg-6" required
                           help="Mức điền sẵn khi lên hóa đơn.">
                <x-admin.money-input name="price" :value="$service->price" required />
            </x-admin.field>

            <div class="col-12">
                <p class="small text-body-secondary mb-0">
                    Nếu dịch vụ tính tiền theo độ khó (vẽ móng, charm đá, phá gel…) thì khai thêm khoảng giá
                    dưới đây. Để trống nghĩa là giá cố định.
                </p>
            </div>

            <x-admin.field name="price_min" label="Giá sàn" col="col-6 col-lg-3">
                <x-admin.money-input name="price_min" :value="$service->price_min" />
            </x-admin.field>

            <x-admin.field name="price_max" label="Giá trần" col="col-6 col-lg-3">
                <x-admin.money-input name="price_max" :value="$service->price_max" />
            </x-admin.field>

            <x-admin.field name="display_order" label="Thứ tự trong nhóm" col="col-6 col-lg-3"
                           help="Số nhỏ hiện trước.">
                <input class="form-control neo-num @error('display_order') is-invalid @enderror" id="display_order"
                       name="display_order" type="number" min="0" max="999"
                       value="{{ old('display_order', $service->display_order ?? 0) }}">
            </x-admin.field>

            <x-admin.field name="description" label="Mô tả ngắn" col="col-12">
                <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="3"
                          placeholder="Ví dụ: Sơn gel, chăm sóc móng, đắp bột…">{{ old('description', $service->description) }}</textarea>
            </x-admin.field>

            <div class="col-12">
                <label class="form-check card px-3 py-2 mb-0 d-flex flex-row align-items-center gap-2">
                    <input class="form-check-input m-0 flex-shrink-0" type="checkbox" name="is_active" value="1"
                           @checked(old('is_active', $service->exists ? $service->is_active : true))>
                    <span>Đang áp dụng và hiển thị trong form đặt lịch</span>
                </label>
            </div>
        </div>

        <div class="neo-formbar">
            <a href="{{ route('admin.services.index') }}" class="btn btn-light">Hủy</a>
            <x-admin.submit-button :label="$service->exists ? 'Lưu thay đổi' : 'Thêm dịch vụ'" />
        </div>
    </form>
</x-layouts.admin>
