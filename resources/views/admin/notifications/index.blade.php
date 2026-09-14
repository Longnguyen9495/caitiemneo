<x-layouts.admin title="Thông báo" heading="Thông báo">
    <x-admin.page-header
        title="Thông báo"
        description="Các cập nhật về đơn nghỉ, đổi ca và phân người thay của bạn."
    />

    <section class="card overflow-hidden">
        <div class="list-group list-group-flush">
            @forelse ($notifications as $notification)
                @php
                    $data = $notification->data;
                    $url = $data['url'] ?? route('admin.shift-requests.index');
                @endphp
                <a href="{{ $url }}" class="list-group-item list-group-item-action py-3">
                    <div class="d-flex gap-3 align-items-start">
                        <span class="badge {{ $notification->read_at ? 'text-bg-light border' : 'text-bg-warning' }} mt-1">
                            {{ $notification->read_at ? 'Đã xem' : 'Mới' }}
                        </span>
                        <div class="flex-grow-1">
                            <p class="mb-1 fw-semibold">{{ $data['message'] ?? 'Có cập nhật mới về đơn ca làm.' }}</p>
                            @if (isset($data['work_date']))
                                <p class="mb-0 small text-body-secondary">Ngày làm: {{ \Illuminate\Support\Carbon::parse($data['work_date'])->format('d/m/Y') }}</p>
                            @endif
                        </div>
                        <time class="small text-body-secondary text-nowrap" datetime="{{ $notification->created_at->toAtomString() }}">
                            {{ $notification->created_at->format('d/m/Y H:i') }}
                        </time>
                    </div>
                </a>
            @empty
                <x-admin.empty-state title="Chưa có thông báo" hint="Các cập nhật về đơn nghỉ và đổi ca sẽ xuất hiện tại đây." />
            @endforelse
        </div>
    </section>

    @if ($notifications->hasPages())
        <div class="mt-3">{{ $notifications->links() }}</div>
    @endif
</x-layouts.admin>
