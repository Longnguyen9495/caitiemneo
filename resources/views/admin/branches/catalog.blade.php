<x-layouts.admin :title="'Danh mục '.$branch->name" heading="Chi nhánh">
    <x-admin.page-header
        :title="'Danh mục của '.$branch->name"
        description="Giá, thời lượng và hoa hồng có thể khác nhau giữa các chi nhánh. Bỏ chọn để ngừng cung cấp tại chi nhánh này."
        :breadcrumbs="['Chi nhánh' => route('admin.branches.index'), $branch->code => null]"
    />

    <form method="POST" action="{{ route('admin.branches.catalog.update', $branch) }}">
        @csrf
        @method('PATCH')

        <section class="admin-panel">
            <header class="admin-panel-header">
                <div><h2>Dịch vụ</h2><p>Giá và hoa hồng áp dụng riêng cho chi nhánh này.</p></div>
            </header>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Cung cấp</th>
                            <th>Dịch vụ</th>
                            <th class="admin-numeric">Giá</th>
                            <th class="admin-numeric">Thời lượng (phút)</th>
                            <th class="admin-numeric">Hoa hồng %</th>
                            <th class="admin-numeric">Ngoài giờ %</th>
                            <th>Hiển thị</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($services as $service)
                            @php $row = $branchServices->get($service->id); @endphp
                            <tr>
                                <td><input type="checkbox" name="services[{{ $service->id }}][enabled]" value="1" @checked(old("services.{$service->id}.enabled", $row !== null))></td>
                                <td><strong>{{ $service->name }}</strong><p>Giá chung: {{ \App\Support\Money::format($service->price) }}</p></td>
                                <td class="admin-numeric"><input class="admin-money-input" type="number" step="1000" min="0" name="services[{{ $service->id }}][price]" value="{{ old("services.{$service->id}.price", $row?->price ?? $service->price) }}"></td>
                                <td class="admin-numeric"><input class="admin-money-input" type="number" step="5" min="5" max="480" name="services[{{ $service->id }}][duration_minutes]" value="{{ old("services.{$service->id}.duration_minutes", $row?->duration_minutes ?? 60) }}"></td>
                                <td class="admin-numeric"><input class="admin-money-input" type="number" step="0.5" min="0" max="100" name="services[{{ $service->id }}][commission_rate]" value="{{ old("services.{$service->id}.commission_rate", $row?->commission_rate) }}" placeholder="Theo hồ sơ"></td>
                                <td class="admin-numeric"><input class="admin-money-input" type="number" step="0.5" min="0" max="100" name="services[{{ $service->id }}][overtime_commission_rate]" value="{{ old("services.{$service->id}.overtime_commission_rate", $row?->overtime_commission_rate) }}" placeholder="Theo hồ sơ"></td>
                                <td><input type="checkbox" name="services[{{ $service->id }}][is_active]" value="1" @checked(old("services.{$service->id}.is_active", $row?->is_active ?? true))></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="admin-panel">
            <header class="admin-panel-header">
                <div><h2>Vật tư</h2><p>Định mức tồn tối thiểu tính riêng cho kho của chi nhánh.</p></div>
            </header>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead><tr><th>Lưu kho</th><th>Vật tư</th><th class="admin-numeric">Định mức tồn</th><th>Đang dùng</th></tr></thead>
                    <tbody>
                        @foreach ($products as $product)
                            @php $row = $branchProducts->get($product->id); @endphp
                            <tr>
                                <td><input type="checkbox" name="products[{{ $product->id }}][enabled]" value="1" @checked(old("products.{$product->id}.enabled", $row !== null))></td>
                                <td><strong>{{ $product->name }}</strong><p>{{ $product->unit }}</p></td>
                                <td class="admin-numeric"><input class="admin-money-input" type="number" step="0.01" min="0" name="products[{{ $product->id }}][minimum_stock]" value="{{ old("products.{$product->id}.minimum_stock", $row?->minimum_stock ?? $product->minimum_stock) }}"></td>
                                <td><input type="checkbox" name="products[{{ $product->id }}][is_active]" value="1" @checked(old("products.{$product->id}.is_active", $row?->is_active ?? true))></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="admin-panel-body">
                <div class="admin-form-actions">
                    <a href="{{ route('admin.branches.index') }}">Quay lại</a>
                    <x-admin.submit-button label="Lưu danh mục chi nhánh" />
                </div>
            </div>
        </section>
    </form>
</x-layouts.admin>
