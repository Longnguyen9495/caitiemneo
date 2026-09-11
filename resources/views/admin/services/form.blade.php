<x-layouts.admin :title="$service->exists ? 'Sửa dịch vụ' : 'Thêm dịch vụ'" :heading="$service->exists ? 'Chỉnh sửa dịch vụ' : 'Thêm dịch vụ'">
    <section class="admin-form-panel">
        <x-admin.page-header
            :title="$service->exists ? $service->name : 'Tạo dịch vụ mới'"
            description="Thông tin này sẽ được dùng trong lịch hẹn và hóa đơn dịch vụ."
            :breadcrumbs="['Dịch vụ' => route('admin.services.index'), ($service->exists ? 'Chỉnh sửa' : 'Thêm mới') => null]"
        />

        <form method="POST" action="{{ $service->exists ? route('admin.services.update', $service) : route('admin.services.store') }}" class="admin-form">
            @csrf
            @if ($service->exists)
                @method('PUT')
            @endif

            <label>
                Tên dịch vụ
                <input name="name" value="{{ old('name', $service->name) }}" required autofocus>
                @error('name')<small>{{ $message }}</small>@enderror
            </label>

            <label>
                Giá niêm yết (VNĐ)
                <x-admin.money-input name="price" :value="$service->price" required />
                @error('price')<small>{{ $message }}</small>@enderror
            </label>

            <label class="admin-form-wide">
                Mô tả ngắn
                <textarea name="description" rows="4" placeholder="Ví dụ: Sơn gel, chăm sóc móng, đắp bột…">{{ old('description', $service->description) }}</textarea>
                @error('description')<small>{{ $message }}</small>@enderror
            </label>

            <label class="admin-checkbox admin-form-wide">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $service->exists ? $service->is_active : true))>
                <span>Đang áp dụng và hiển thị trong form đặt lịch</span>
            </label>

            <div class="admin-form-actions admin-form-wide">
                <a href="{{ route('admin.services.index') }}">Hủy</a>
                <x-admin.submit-button :label="$service->exists ? 'Lưu thay đổi' : 'Thêm dịch vụ'" />
            </div>
        </form>
    </section>
</x-layouts.admin>
