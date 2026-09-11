@if ($logs->isNotEmpty())
    <section class="card overflow-hidden mt-3">
        <div class="p-3 border-bottom">
            <h2 class="fs-6 fw-semibold mb-0">Nhật ký của ca này</h2>
        </div>

        <table class="table neo-table align-middle mb-0">
            <thead>
                <tr>
                    <th scope="col">Thời điểm</th>
                    <th scope="col">Thao tác</th>
                    <th scope="col">Người thực hiện</th>
                    <th scope="col">Lý do / thay đổi</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($logs as $log)
                    <tr>
                        <td class="neo-num neo-doc-no">{{ $log->created_at->format('d/m/Y H:i') }}</td>
                        <td data-label="Thao tác"><x-admin.status-badge :label="$log->action->label()" :tone="$log->action->tone()" /></td>
                        <td data-label="Người thực hiện">{{ $log->actor?->name ?? 'Hệ thống' }}</td>
                        <td data-label="Lý do / thay đổi">
                            @if ($log->reason)<div class="small">{{ $log->reason }}</div>@endif
                            @foreach ($log->changes() as $field => $change)
                                <small class="d-block text-body-secondary">
                                    {{ $field }}: {{ $change['before'] ?? '—' }} → {{ $change['after'] ?? '—' }}
                                </small>
                            @endforeach
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>
@endif
