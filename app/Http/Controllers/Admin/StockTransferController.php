<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Inventory\CompleteStockTransferAction;
use App\Enums\StockTransferStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StockTransferRequest;
use App\Models\Branch;
use App\Models\Product;
use App\Models\StockTransfer;
use App\Support\BranchContext;
use App\Support\DocumentNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StockTransferController extends Controller
{
    public function index(Request $request, BranchContext $branchContext): View
    {
        $this->authorize('viewAny', StockTransfer::class);

        $branchIds = $branchContext->scopeIds();

        return view('admin.transfers.index', [
            'transfers' => StockTransfer::query()
                ->with(['sourceBranch', 'destinationBranch', 'creator'])
                ->withCount('items')
                ->when($branchIds !== [], fn ($query) => $query->forBranches($branchIds))
                ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
                ->latest('id')
                ->paginate(20)
                ->withQueryString(),
            'statuses' => StockTransferStatus::options(),
        ]);
    }

    public function create(BranchContext $branchContext): View
    {
        $this->authorize('create', StockTransfer::class);

        return view('admin.transfers.form', [
            'sourceBranches' => $branchContext->available(),
            'destinationBranches' => Branch::query()->active()->orderBy('code')->get(),
            'products' => Product::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(StockTransferRequest $request): RedirectResponse
    {
        $transfer = DB::transaction(function () use ($request): StockTransfer {
            $sourceBranch = Branch::query()->findOrFail($request->validated('source_branch_id'));

            $transfer = StockTransfer::query()->create([
                'number' => DocumentNumber::forStockTransfer($sourceBranch->code),
                'source_branch_id' => $sourceBranch->getKey(),
                'destination_branch_id' => $request->validated('destination_branch_id'),
                'status' => StockTransferStatus::Draft,
                'created_by' => $request->user()->getKey(),
                'note' => $request->validated('note'),
            ]);

            foreach ($request->validated('items', []) as $row) {
                $transfer->items()->create([
                    'product_id' => $row['product_id'],
                    'quantity' => $row['quantity'],
                    'unit_cost' => $row['unit_cost'] ?? 0,
                ]);
            }

            return $transfer;
        });

        return redirect()->route('admin.stock-transfers.show', $transfer)
            ->with('success', 'Đã tạo phiếu chuyển kho nháp.');
    }

    public function show(StockTransfer $stockTransfer): View
    {
        $this->authorize('view', $stockTransfer);

        $stockTransfer->load(['items.product', 'sourceBranch', 'destinationBranch', 'creator', 'completer']);

        return view('admin.transfers.show', ['transfer' => $stockTransfer]);
    }

    public function complete(Request $request, StockTransfer $stockTransfer, CompleteStockTransferAction $complete): RedirectResponse
    {
        $this->authorize('complete', $stockTransfer);

        $complete->handle($stockTransfer, $request->user());

        return redirect()->route('admin.stock-transfers.show', $stockTransfer)
            ->with('success', 'Đã hoàn tất chuyển kho và ghi nhận phiếu xuất, phiếu nhập.');
    }

    public function cancel(Request $request, StockTransfer $stockTransfer): RedirectResponse
    {
        $this->authorize('cancel', $stockTransfer);

        $stockTransfer->forceFill([
            'status' => StockTransferStatus::Cancelled,
            'cancelled_at' => now(),
            'cancelled_by' => $request->user()->getKey(),
        ])->save();

        return redirect()->route('admin.stock-transfers.show', $stockTransfer)
            ->with('success', 'Đã hủy phiếu chuyển kho.');
    }
}
