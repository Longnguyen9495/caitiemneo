<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductRequest;
use App\Models\Product;
use App\Support\BranchContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(private BranchContext $branchContext) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Product::class);

        // In the company-wide view the balance is the sum over every shop; with
        // a branch selected it is that shop's own stock.
        $branchId = $this->branchContext->viewingAll() ? null : $this->branchContext->currentId();

        $products = Product::query()
            ->withCurrentStock($branchId)
            ->withBranchMinimum($branchId)
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $term = '%'.$request->string('search')->toString().'%';

                $query->where(fn (Builder $inner) => $inner->where('name', 'like', $term)->orWhere('sku', 'like', $term));
            })
            ->when($request->filled('status'), fn (Builder $query) => $query->where('is_active', $request->string('status')->toString() === 'active'))
            ->when($request->boolean('low_stock'), fn (Builder $query) => $query->lowStock($branchId))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.products.index', [
            'products' => $products,
            'branchLabel' => $this->branchContext->viewingAll()
                ? 'toàn hệ thống'
                : ($this->branchContext->current()?->name ?? ''),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Product::class);

        return view('admin.products.form', ['product' => new Product(['unit' => 'đơn vị'])]);
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        Product::query()->create($request->validated());

        return redirect()->route('admin.products.index')->with('success', 'Đã thêm vật tư mới.');
    }

    public function edit(Product $product): View
    {
        $this->authorize('update', $product);

        return view('admin.products.form', ['product' => $product]);
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $product->update($request->validated());

        return redirect()->route('admin.products.index')->with('success', 'Đã cập nhật vật tư.');
    }
}
