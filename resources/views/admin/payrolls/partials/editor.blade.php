<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <h2>Thêm khoản điều chỉnh</h2>
            <p>Số tiền luôn nhập dương; chiều cộng hay trừ do trường "Loại" quyết định.</p>
        </div>
    </header>

    <form method="POST" action="{{ route('admin.payrolls.adjustments.store', $payroll) }}" class="admin-form" style="padding: 1.25rem 1.4rem;">
        @csrf

        <label>
            Hạng mục
            <select name="category" required>
                @foreach ($adjustmentCategories as $value => $label)
                    <option value="{{ $value }}" @selected(old('category') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('category')<small>{{ $message }}</small>@enderror
        </label>

        <label>
            Loại
            <select name="direction" required>
                @foreach ($adjustmentDirections as $value => $label)
                    <option value="{{ $value }}" @selected(old('direction') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('direction')<small>{{ $message }}</small>@enderror
        </label>

        <label>
            Số tiền (VNĐ)
            <x-admin.money-input name="amount" min="1" required />
            @error('amount')<small>{{ $message }}</small>@enderror
        </label>

        <label>
            Chi nhánh chịu chi phí
            <select name="branch_id">
                <option value="">Tính vào toàn kỳ</option>
                @foreach ($payroll->allocations as $allocation)
                    <option value="{{ $allocation->branch_id }}" @selected((string) old('branch_id') === (string) $allocation->branch_id)>{{ $allocation->branch?->name }}</option>
                @endforeach
            </select>
            @error('branch_id')<small>{{ $message }}</small>@enderror
        </label>

        <label class="admin-form-wide">
            Diễn giải
            <input name="description" value="{{ old('description') }}" required maxlength="255" placeholder="Ví dụ: Phụ cấp xăng xe tháng 8">
            @error('description')<small>{{ $message }}</small>@enderror
        </label>

        <div class="admin-form-actions admin-form-wide">
            <x-admin.submit-button label="Thêm khoản" />
        </div>
    </form>
</section>
