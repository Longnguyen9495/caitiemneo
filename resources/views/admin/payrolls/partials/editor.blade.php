<section class="card mb-3">
    <div class="card-header">
        <h2 class="neo-display fs-5 mb-0">Thêm khoản điều chỉnh</h2>
        <p class="mb-0 small text-body-secondary">Số tiền luôn nhập dương; chiều cộng hay trừ do trường "Loại" quyết định.</p>
    </div>

    <form method="POST" action="{{ route('admin.payrolls.adjustments.store', $payroll) }}" class="card-body">
        @csrf

        <div class="row g-3">
            <x-admin.field name="category" label="Hạng mục" required>
                <select class="form-select @error('category') is-invalid @enderror" id="category" name="category" required>
                    @foreach ($adjustmentCategories as $value => $label)
                        <option value="{{ $value }}" @selected(old('category') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </x-admin.field>

            <x-admin.field name="direction" label="Loại" required>
                <select class="form-select @error('direction') is-invalid @enderror" id="direction" name="direction" required>
                    @foreach ($adjustmentDirections as $value => $label)
                        <option value="{{ $value }}" @selected(old('direction') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </x-admin.field>

            <x-admin.field name="amount" label="Số tiền" required>
                <x-admin.money-input name="amount" min="1" required />
            </x-admin.field>

            <x-admin.field name="branch_id" label="Chi nhánh chịu chi phí">
                <select class="form-select @error('branch_id') is-invalid @enderror" id="branch_id" name="branch_id">
                    <option value="">Tính vào toàn kỳ</option>
                    @foreach ($payroll->allocations as $allocation)
                        <option value="{{ $allocation->branch_id }}" @selected((string) old('branch_id') === (string) $allocation->branch_id)>{{ $allocation->branch?->name }}</option>
                    @endforeach
                </select>
            </x-admin.field>

            <x-admin.field name="description" label="Diễn giải" col="col-12" required>
                <input class="form-control @error('description') is-invalid @enderror" id="description" name="description"
                       value="{{ old('description') }}" required maxlength="255" placeholder="Ví dụ: Phụ cấp xăng xe tháng 8">
            </x-admin.field>
        </div>

        <div class="d-grid d-lg-flex justify-content-lg-end mt-3">
            <x-admin.submit-button label="Thêm khoản" />
        </div>
    </form>
</section>
