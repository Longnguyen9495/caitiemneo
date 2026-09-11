<x-layouts.admin :title="'Hóa đơn '.$invoice->number" heading="Chi tiết hóa đơn">
    <x-admin.page-header
        :title="$invoice->customer_name ?: 'Khách lẻ'"
        :description="$invoice->customer_phone ?: 'Chưa có số điện thoại'"
        :breadcrumbs="['Hóa đơn' => route('admin.invoices.index'), $invoice->number => null]"
    >
        <x-slot:actions>
            <x-admin.status-badge :status="$invoice->status" />
            @if ($invoice->appointment)
                <a class="admin-button is-ghost" href="{{ route('admin.appointments.index', ['date' => $invoice->appointment->starts_at->toDateString()]) }}">Lịch hẹn gốc</a>
            @endif
        </x-slot:actions>
    </x-admin.page-header>

    @include('admin.invoices.partials.form')

    @include('admin.invoices.partials.settlement')

    @include('admin.invoices.partials.bill-kpi')

    @include('admin.invoices.partials.transactions')
</x-layouts.admin>
