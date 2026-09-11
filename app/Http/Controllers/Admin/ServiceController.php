<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ServiceCategory;
use App\Enums\ServiceUnit;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ServiceRequest;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiceController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Service::class);

        $services = Service::query()
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%'.$request->string('search')->toString().'%'))
            ->when($request->filled('status'), fn ($query) => $query->where('is_active', $request->string('status')->toString() === 'active'))
            ->when($request->filled('category'), fn ($query) => $query->inCategory($request->string('category')->toString()))
            ->orderByDesc('is_active')
            ->inMenuOrder()
            // 30 để cả bảng giá 26 dịch vụ nằm gọn một trang, không bị cắt
            // ngang giữa một nhóm.
            ->paginate(30)
            ->withQueryString();

        return view('admin.services.index', ['services' => $services]);
    }

    public function create(): View
    {
        $this->authorize('create', Service::class);

        return view('admin.services.form', [
            'service' => new Service([
                'category' => ServiceCategory::BasicNail,
                'unit' => ServiceUnit::Set,
                'is_active' => true,
            ]),
        ]);
    }

    public function store(ServiceRequest $request): RedirectResponse
    {
        Service::query()->create($request->validated());

        return redirect()->route('admin.services.index')->with('success', 'Đã thêm dịch vụ mới.');
    }

    public function edit(Service $service): View
    {
        $this->authorize('update', $service);

        return view('admin.services.form', compact('service'));
    }

    public function update(ServiceRequest $request, Service $service): RedirectResponse
    {
        $service->update($request->validated());

        return redirect()->route('admin.services.index')->with('success', 'Đã cập nhật dịch vụ.');
    }
}
