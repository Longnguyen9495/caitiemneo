<x-layouts.admin :title="'Danh mục '.$branch->name" heading="Danh mục chi nhánh">
    <x-admin.page-header
        :title="'Danh mục của '.$branch->name"
        description="Giá, thời lượng và hoa hồng có thể khác nhau giữa các chi nhánh. Bỏ chọn để ngừng cung cấp tại chi nhánh này."
        :breadcrumbs="['Chi nhánh' => route('admin.branches.index'), $branch->code => null]"
    />

    <form method="POST" action="{{ route('admin.branches.catalog.update', $branch) }}">
        @csrf
        @method('PATCH')

        <section class="card mb-3">
            <div class="card-header">
                <h2 class="neo-display fs-5 mb-0">Dịch vụ</h2>
                <p class="mb-0 small text-body-secondary">Giá và hoa hồng áp dụng riêng cho chi nhánh này.</p>
            </div>

            <div class="card-body">
                @foreach ($services as $service)
                    @php $row = $branchServices->get($service->id); @endphp
                    <fieldset class="border rounded-3 p-3 mb-2">
                        <legend class="float-none w-auto px-1 fs-6 fw-semibold mb-2">
                            <label class="form-check d-inline-flex align-items-center gap-2 mb-0">
                                <input class="form-check-input m-0" type="checkbox" name="services[{{ $service->id }}][enabled]" value="1"
                                       @checked(old("services.{$service->id}.enabled", $row !== null))>
                                <span>{{ $service->name }}</span>
                            </label>
                        </legend>

                        <div class="row g-2">
                            <div class="col-6 col-lg-3">
                                <label class="form-label" for="svc-price-{{ $service->id }}">Giá</label>
                                <input class="form-control text-end neo-num" id="svc-price-{{ $service->id }}" type="number" step="1000" min="0"
                                       name="services[{{ $service->id }}][price]"
                                       value="{{ old('services.'.$service->id.'.price', $row?->price ?? $service->price) }}">
                            </div>
                            <div class="col-6 col-lg-3">
                                <label class="form-label" for="svc-dur-{{ $service->id }}">Thời lượng (phút)</label>
                                <input class="form-control text-end neo-num" id="svc-dur-{{ $service->id }}" type="number" step="5" min="5" max="480"
                                       name="services[{{ $service->id }}][duration_minutes]"
                                       value="{{ old('services.'.$service->id.'.duration_minutes', $row?->duration_minutes ?? 60) }}">
                            </div>
                            <div class="col-6 col-lg-2">
                                <label class="form-label" for="svc-rate-{{ $service->id }}">Hoa hồng %</label>
                                <input class="form-control text-end neo-num" id="svc-rate-{{ $service->id }}" type="number" step="0.5" min="0" max="100"
                                       name="services[{{ $service->id }}][commission_rate]"
                                       value="{{ old('services.'.$service->id.'.commission_rate', $row?->commission_rate) }}" placeholder="Theo hồ sơ">
                            </div>
                            <div class="col-6 col-lg-2">
                                <label class="form-label" for="svc-ot-{{ $service->id }}">Ngoài giờ %</label>
                                <input class="form-control text-end neo-num" id="svc-ot-{{ $service->id }}" type="number" step="0.5" min="0" max="100"
                                       name="services[{{ $service->id }}][overtime_commission_rate]"
                                       value="{{ old('services.'.$service->id.'.overtime_commission_rate', $row?->overtime_commission_rate) }}" placeholder="Theo hồ sơ">
                            </div>
                            <div class="col-12 col-lg-2 d-flex align-items-end">
                                <label class="form-check d-inline-flex align-items-center gap-2 mb-2">
                                    <input class="form-check-input m-0" type="checkbox" name="services[{{ $service->id }}][is_active]" value="1"
                                           @checked(old('services.'.$service->id.'.is_active', $row?->is_active ?? true))>
                                    <span class="small">Hiển thị</span>
                                </label>
                            </div>
                        </div>
                    </fieldset>
                @endforeach
            </div>
        </section>

        <section class="card">
            <div class="card-header">
                <h2 class="neo-display fs-5 mb-0">Vật tư</h2>
                <p class="mb-0 small text-body-secondary">Định mức tồn tối thiểu tính riêng cho kho của chi nhánh.</p>
            </div>

            <table class="table neo-table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">Lưu kho</th>
                        <th scope="col">Đơn vị</th>
                        <th scope="col" class="text-end">Định mức tồn</th>
                        <th scope="col">Đang dùng</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($products as $product)
                        @php $row = $branchProducts->get($product->id); @endphp
                        <tr>
                            <td>
                                <label class="form-check d-inline-flex align-items-center gap-2 mb-0">
                                    <input class="form-check-input m-0" type="checkbox" name="products[{{ $product->id }}][enabled]" value="1"
                                           @checked(old('products.'.$product->id.'.enabled', $row !== null))>
                                    <span class="fw-semibold">{{ $product->name }}</span>
                                </label>
                            </td>
                            <td data-label="Đơn vị">{{ $product->unit }}</td>
                            <td data-label="Định mức tồn" class="text-end">
                                <input class="form-control form-control-sm text-end neo-num d-inline-block" style="max-width:7rem"
                                       type="number" step="0.01" min="0" name="products[{{ $product->id }}][minimum_stock]"
                                       aria-label="Định mức tồn của {{ $product->name }}"
                                       value="{{ old('products.'.$product->id.'.minimum_stock', $row?->minimum_stock ?? $product->minimum_stock) }}">
                            </td>
                            <td data-label="Đang dùng">
                                <input class="form-check-input m-0" type="checkbox" name="products[{{ $product->id }}][is_active]" value="1"
                                       aria-label="Đang dùng {{ $product->name }}"
                                       @checked(old('products.'.$product->id.'.is_active', $row?->is_active ?? true))>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="card-body border-top">
                <div class="neo-formbar">
                    <a href="{{ route('admin.branches.index') }}" class="btn btn-light">Quay lại</a>
                    <x-admin.submit-button label="Lưu danh mục" />
                </div>
            </div>
        </section>
    </form>
</x-layouts.admin>
