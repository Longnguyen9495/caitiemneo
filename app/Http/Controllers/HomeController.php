<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\BranchService;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $branches = Branch::query()->active()->orderBy('code')->get();

        return view('home', [
            'branches' => $branches,
            // The public page lists what each shop actually offers, at that
            // shop's own price.
            'branchServices' => BranchService::query()
                ->with('service')
                ->whereIn('branch_id', $branches->pluck('id'))
                ->where('is_active', true)
                ->get()
                ->groupBy('branch_id'),
            'services' => BranchService::query()
                ->with('service')
                ->where('is_active', true)
                ->get()
                ->pluck('service')
                ->filter(fn (?object $service): bool => (bool) $service?->is_active)
                ->unique('id')
                ->sortBy('name')
                ->values(),
        ]);
    }
}
