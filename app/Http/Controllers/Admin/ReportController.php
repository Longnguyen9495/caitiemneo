<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Services\ReportService;
use App\Support\BranchContext;
use App\Support\ReportPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function __construct(private BranchContext $branchContext) {}

    public function __invoke(Request $request, ReportService $reports): View
    {
        Gate::authorize('view-reports');

        $period = ReportPeriod::fromRequest($request);

        // Reporting always filters to the branches the viewer may see, so the
        // company-wide view of an owner and the single-shop view of a manager
        // run the exact same queries with a different id list.
        $branchIds = $this->branchContext->scopeIds() ?: [0];

        $appointmentsByStatus = $reports->appointmentsByStatus($period, $branchIds);
        $totalAppointments = array_sum($appointmentsByStatus);

        $lostAppointments = ($appointmentsByStatus[AppointmentStatus::Cancelled->value] ?? 0)
            + ($appointmentsByStatus[AppointmentStatus::NoShow->value] ?? 0);

        $comparableBranches = $this->branchContext->available();

        return view('admin.reports.index', [
            'period' => $period,
            'summary' => $reports->summary($period, $branchIds),
            'commission' => $reports->commissionTotals($period, $branchIds),
            'kpiBonus' => $reports->kpiBonusTotal($period, $branchIds),
            'appointmentsByStatus' => $appointmentsByStatus,
            'totalAppointments' => $totalAppointments,
            'lostRate' => $totalAppointments > 0 ? round($lostAppointments / $totalAppointments * 100, 1) : 0.0,
            'revenueByService' => $reports->revenueByService($period, 15, $branchIds),
            'revenueByEmployee' => $reports->revenueByEmployee($period, 15, $branchIds),
            'branchComparison' => $comparableBranches->count() > 1
                ? $reports->branchComparison($period, $comparableBranches)
                : collect(),
            'canSeeProfit' => Gate::allows('view-profit-reports'),
            'scopeLabel' => $this->branchContext->viewingAll()
                ? 'Toàn hệ thống'
                : ($this->branchContext->current()?->name ?? ''),
        ]);
    }
}
