<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Inventory\RecordInventoryMovementAction;
use App\Enums\InventoryMovementType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\InventoryMovementRequest;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Supplier;
use App\Queries\InventoryMovementQuery;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryMovementController extends Controller
{
    public function index(Request $request, InventoryMovementQuery $movements): View
    {
        $this->authorize('viewAny', InventoryMovement::class);

        return view('admin.inventory.index', [
            'movements' => $movements->build($request)
                ->with(['product', 'supplier', 'creator'])
                ->latest('occurred_at')
                ->latest('id')
                ->paginate(25)
                ->withQueryString(),
            'products' => $this->products(),
            'suppliers' => $this->suppliers(),
            'types' => InventoryMovementType::options(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', InventoryMovement::class);

        return view('admin.inventory.form', [
            'products' => Product::query()->where('is_active', true)->withCurrentStock()->orderBy('name')->get(),
            'suppliers' => $this->suppliers(),
            'types' => InventoryMovementType::options(),
            'selectedProductId' => $request->integer('product_id') ?: null,
            'selectedType' => $request->string('type')->toString() ?: InventoryMovementType::In->value,
        ]);
    }

    public function store(InventoryMovementRequest $request, RecordInventoryMovementAction $record): RedirectResponse
    {
        $record->handle($request->validated(), $request->user());

        return redirect()->route('admin.inventory.index')->with('success', 'Đã ghi nhận phiếu kho.');
    }

    /** @return Collection<int, Product> */
    private function products()
    {
        return Product::query()->orderBy('name')->get(['id', 'name', 'unit']);
    }

    /** @return Collection<int, Supplier> */
    private function suppliers()
    {
        return Supplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
    }
}
