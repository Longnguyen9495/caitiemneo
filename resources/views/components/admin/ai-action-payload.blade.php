@props(['proposal'])

{{-- Người duyệt cần đọc được thao tác sắp chạy mà không phải hiểu cấu trúc dữ
     liệu: nhãn tiếng Việt, tên thật thay cho ID, ngày giờ và tiền viết như
     trong sổ. Payload gốc vẫn giữ nguyên cho executor. --}}
@php($rows = app(App\Services\Ai\AiPayloadPresenter::class)->rows($proposal))

@if ($rows !== [])
    <details class="ai-action-details mt-3">
        <summary>Xem dữ liệu sẽ áp dụng</summary>
        <dl class="row g-2 mb-0 mt-1 small">
            @foreach ($rows as $row)
                <dt class="col-5 text-body-secondary">{{ $row['label'] }}</dt>
                <dd class="col-7 mb-0 text-break">{{ $row['value'] }}</dd>
            @endforeach
        </dl>
    </details>
@endif
