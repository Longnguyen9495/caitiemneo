<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BranchRequest;
use App\Models\Branch;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BranchController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Branch::class);

        return view('admin.branches.index', [
            'branches' => Branch::query()
                ->withCount(['assignments', 'invoices'])
                ->orderByDesc('is_active')
                ->orderBy('code')
                ->paginate(20),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Branch::class);

        return view('admin.branches.form', ['branch' => new Branch(['is_active' => true])]);
    }

    public function store(BranchRequest $request): RedirectResponse
    {
        Branch::query()->create($request->validated());

        return redirect()->route('admin.branches.index')->with('success', 'Đã thêm chi nhánh.');
    }

    public function edit(Branch $branch): View
    {
        $this->authorize('update', $branch);

        return view('admin.branches.form', ['branch' => $branch]);
    }

    public function update(BranchRequest $request, Branch $branch): RedirectResponse
    {
        $branch->update($request->validated());

        return redirect()->route('admin.branches.index')->with('success', 'Đã cập nhật chi nhánh.');
    }
}
