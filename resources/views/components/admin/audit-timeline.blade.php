@props(['events', 'title' => 'Lịch sử thay đổi', 'description' => 'Ai đã thay đổi gì, lúc nào và vì sao.'])

{{--
    Nhật ký chỉ đọc. Bảng audit không có route ghi nào, và view này cũng chỉ
    hiển thị: mọi chỉnh sửa đều là một event mới, không sửa đè event cũ.
--}}
<section class="card mt-3" aria-labelledby="neo-audit-timeline">
    <div class="card-header">
        <h2 id="neo-audit-timeline" class="neo-display fs-5 mb-0">{{ $title }}</h2>
        <p class="mb-0 small text-body-secondary">{{ $description }}</p>
    </div>

    @forelse ($events as $event)
        @php($changes = $event->changes())

        <article class="border-0 border-bottom px-3 py-3">
            <div class="d-flex flex-wrap align-items-baseline gap-2">
                <span class="badge rounded-pill text-bg-light border">{{ $event->action->label() }}</span>
                <strong>{{ $event->actorLabel() }}</strong>
                <time class="small text-body-secondary" datetime="{{ $event->created_at?->toIso8601String() }}">
                    {{ $event->created_at?->format('d/m/Y H:i') }}
                </time>
            </div>

            @if ($event->reason)
                <p class="mb-1 mt-2 small">Lý do: {{ $event->reason }}</p>
            @endif

            @if ($changes)
                <dl class="row row-cols-1 g-1 mb-0 mt-2 small">
                    @foreach ($changes as $field => $pair)
                        <div class="col">
                            <dt class="d-inline fw-semibold">{{ \App\Support\AuditDictionary::field($field) }}:</dt>
                            <dd class="d-inline mb-0 text-body-secondary">
                                <del>{{ \App\Support\AuditDictionary::value($pair['before'], $field, $event->auditable_type) }}</del>
                                <span aria-hidden="true">→</span>
                                <span class="visually-hidden">đổi thành</span>
                                <ins class="text-decoration-none fw-semibold">{{ \App\Support\AuditDictionary::value($pair['after'], $field, $event->auditable_type) }}</ins>
                            </dd>
                        </div>
                    @endforeach
                </dl>
            @endif
        </article>
    @empty
        <x-admin.empty-state
            icon="inbox"
            title="Chưa có thay đổi nào được ghi nhận"
            hint="Mọi lần sửa, thanh toán hoặc hủy từ nay sẽ xuất hiện ở đây."
        />
    @endforelse
</section>
