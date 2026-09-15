<x-layouts.admin title="Thông báo" heading="Thông báo">
    <x-admin.page-header
        title="Thông báo"
        description="Các cập nhật dành riêng cho bạn về lịch hẹn, ca làm và những đối tượng bạn phụ trách."
    />

    <section class="card overflow-hidden">
        <div class="list-group list-group-flush">
            @forelse ($notifications as $notification)
                @php
                    $data = $notification->data;
                    $kind = $data['kind'] ?? ($notification->type === App\Notifications\ShiftRequestNotification::class ? 'shift_request' : 'general');
                    $typeLabel = match ($kind) {
                        'online_booking' => 'Lịch hẹn online',
                        'shift_request' => 'Đơn ca làm',
                        default => 'Cập nhật hệ thống',
                    };
                    $title = $data['title'] ?? $typeLabel;
                    $detail = $data['detail'] ?? (isset($data['work_date']) ? 'Ngày làm: '.\Illuminate\Support\Carbon::parse($data['work_date'])->format('d/m/Y') : null);
                @endphp
                <a href="{{ route('admin.notifications.show', $notification) }}" @class(['list-group-item list-group-item-action py-3', 'bg-warning-subtle' => $notification->read_at === null])>
                    <div class="d-flex gap-3 align-items-start">
                        <span class="badge {{ $notification->read_at ? 'text-bg-light border' : 'text-bg-warning' }} mt-1">
                            {{ $typeLabel }}
                        </span>
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <p class="mb-0 fw-semibold">{{ $title }}</p>
                                @if ($notification->read_at === null)
                                    <span class="badge rounded-pill text-bg-danger">Mới</span>
                                @endif
                            </div>
                            <p class="mb-1">{{ $data['message'] ?? 'Có cập nhật mới dành cho bạn.' }}</p>
                            @if ($detail)
                                <p class="mb-0 small text-body-secondary">{{ $detail }}</p>
                            @endif
                        </div>
                        <time class="small text-body-secondary text-nowrap" datetime="{{ $notification->created_at->toAtomString() }}">
                            {{ $notification->created_at->format('d/m/Y H:i') }}
                        </time>
                    </div>
                </a>
            @empty
                <x-admin.empty-state title="Chưa có thông báo" hint="Các cập nhật về lịch hẹn, đơn ca làm và công việc được giao sẽ xuất hiện tại đây." />
            @endforelse
        </div>
    </section>

    @if ($notifications->hasPages())
        <div class="mt-3">{{ $notifications->links() }}</div>
    @endif
</x-layouts.admin>
