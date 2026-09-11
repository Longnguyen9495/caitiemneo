<x-layouts.admin :title="'Bảng lương '.$payroll->employee?->name" heading="Phiếu lương">
    @include('admin.partials.staff-nav')

    <x-admin.page-header
        :title="$payroll->employee?->name ?? 'Bảng lương'"
        :description="'Kỳ '.$payroll->period_start->format('d/m/Y').' — '.$payroll->period_end->format('d/m/Y').($payroll->policy ? ' · '.$payroll->policy->name : '')"
        :breadcrumbs="['Bảng lương' => route('admin.payrolls.index'), 'Chi tiết' => null]"
    >
        <x-slot:actions>
            <x-admin.status-badge :status="$payroll->status" />
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">In phiếu lương</button>
        </x-slot:actions>
    </x-admin.page-header>

    @include('admin.payrolls.partials.payslip')

    @include('admin.payrolls.partials.allocations')

    @include('admin.payrolls.partials.kpi-days')

    @include('admin.payrolls.partials.adjustments')

    @can('update', $payroll)
        @include('admin.payrolls.partials.editor')
    @endcan

    @include('admin.payrolls.partials.actions')
</x-layouts.admin>
