<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BranchCatalogRequest;
use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\BranchService;
use App\Models\Product;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Price, duration, commission and stock thresholds of one branch.
 *
 * The catalogues themselves stay global; only the overlay rows are edited here.
 */
class BranchCatalogController extends Controller
{
    public function edit(Branch $branch): View
    {
        $this->authorize('configure', $branch);

        return view('admin.branches.catalog', [
            'branch' => $branch,
            'services' => Service::query()->orderBy('name')->get(),
            'products' => Product::query()->orderBy('name')->get(),
            'branchServices' => BranchService::query()->where('branch_id', $branch->id)->get()->keyBy('service_id'),
            'branchProducts' => BranchProduct::query()->where('branch_id', $branch->id)->get()->keyBy('product_id'),
        ]);
    }

    public function update(BranchCatalogRequest $request, Branch $branch): RedirectResponse
    {
        DB::transaction(function () use ($request, $branch): void {
            foreach ($request->validated('services', []) as $serviceId => $row) {
                if (! ($row['enabled'] ?? false)) {
                    BranchService::query()->where('branch_id', $branch->id)->where('service_id', $serviceId)->delete();

                    continue;
                }

                BranchService::query()->updateOrCreate(
                    ['branch_id' => $branch->id, 'service_id' => $serviceId],
                    [
                        'price' => $row['price'] ?? 0,
                        'duration_minutes' => $row['duration_minutes'] ?? 60,
                        'commission_rate' => ($row['commission_rate'] ?? null) === '' ? null : ($row['commission_rate'] ?? null),
                        'overtime_commission_rate' => ($row['overtime_commission_rate'] ?? null) === '' ? null : ($row['overtime_commission_rate'] ?? null),
                        'is_active' => (bool) ($row['is_active'] ?? false),
                    ],
                );
            }

            foreach ($request->validated('products', []) as $productId => $row) {
                if (! ($row['enabled'] ?? false)) {
                    BranchProduct::query()->where('branch_id', $branch->id)->where('product_id', $productId)->delete();

                    continue;
                }

                BranchProduct::query()->updateOrCreate(
                    ['branch_id' => $branch->id, 'product_id' => $productId],
                    [
                        'minimum_stock' => $row['minimum_stock'] ?? 0,
                        'is_active' => (bool) ($row['is_active'] ?? false),
                    ],
                );
            }
        });

        return redirect()
            ->route('admin.branches.catalog.edit', $branch)
            ->with('success', 'Đã cập nhật danh mục của chi nhánh.');
    }
}
