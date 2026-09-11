<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Cash\RecordCashTransactionAction;
use App\Actions\Cash\VoidCashTransactionAction;
use App\Enums\CashTransactionCategory;
use App\Enums\CashTransactionType;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CashTransactionRequest;
use App\Http\Requests\Admin\VoidCashTransactionRequest;
use App\Models\CashTransaction;
use App\Queries\CashTransactionQuery;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CashTransactionController extends Controller
{
    public function index(Request $request, CashTransactionQuery $transactions): View
    {
        $this->authorize('viewAny', CashTransaction::class);

        return view('admin.cash.index', [
            'transactions' => $transactions->build($request)
                ->with(['creator', 'invoice', 'payroll.employee', 'branch'])
                ->latest('occurred_at')
                ->latest('id')
                ->paginate(25)
                ->withQueryString(),
            'totals' => $this->totals($request, $transactions),
            'types' => CashTransactionType::options(),
            'categories' => CashTransactionCategory::options(),
            'paymentMethods' => PaymentMethod::options(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', CashTransaction::class);

        return view('admin.cash.form', $this->formData(new CashTransaction([
            'type' => CashTransactionType::Expense,
            'occurred_at' => now(),
        ])));
    }

    public function store(CashTransactionRequest $request, RecordCashTransactionAction $record): RedirectResponse
    {
        $record->handle($request->validated(), $request->user());

        return redirect()->route('admin.cash.index')->with('success', 'Đã ghi nhận giao dịch.');
    }

    public function edit(CashTransaction $cashTransaction): View
    {
        $this->authorize('update', $cashTransaction);

        return view('admin.cash.form', $this->formData($cashTransaction));
    }

    public function update(
        CashTransactionRequest $request,
        CashTransaction $cashTransaction,
        RecordCashTransactionAction $record,
    ): RedirectResponse {
        $record->handle($request->validated(), $request->user(), $cashTransaction);

        return redirect()->route('admin.cash.index')->with('success', 'Đã cập nhật giao dịch.');
    }

    public function destroy(
        VoidCashTransactionRequest $request,
        CashTransaction $cashTransaction,
        VoidCashTransactionAction $void,
    ): RedirectResponse {
        $void->handle($cashTransaction, $request->user(), $request->validated('void_reason'));

        return redirect()->route('admin.cash.index')->with('success', 'Đã hủy giao dịch và giữ lại dấu vết.');
    }

    /**
     * Totals for the current filter, computed by the database in one pass.
     *
     * @return array{income: string, expense: string, balance: string}
     */
    private function totals(Request $request, CashTransactionQuery $transactions): array
    {
        $row = $transactions->build($request)
            ->selectRaw('COALESCE(SUM(CASE WHEN type = ? THEN amount ELSE 0 END), 0) as income_total', [CashTransactionType::Income->value])
            ->selectRaw('COALESCE(SUM(CASE WHEN type = ? THEN amount ELSE 0 END), 0) as expense_total', [CashTransactionType::Expense->value])
            ->first();

        $incomeMinor = Money::toMinor($row?->income_total ?? 0);
        $expenseMinor = Money::toMinor($row?->expense_total ?? 0);

        return [
            'income' => Money::toDecimal($incomeMinor),
            'expense' => Money::toDecimal($expenseMinor),
            'balance' => Money::toDecimal($incomeMinor - $expenseMinor),
        ];
    }

    /** @return array<string, mixed> */
    private function formData(CashTransaction $transaction): array
    {
        return [
            'transaction' => $transaction,
            'types' => CashTransactionType::options(),
            'categories' => CashTransactionCategory::manualOptions(),
            'paymentMethods' => PaymentMethod::options(),
        ];
    }
}
