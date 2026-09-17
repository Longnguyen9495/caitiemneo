<x-layouts.admin title="Trợ lý AI" heading="Trợ lý AI">
    <x-admin.page-header
        title="Trợ lý quản lý Cái Tiệm Neo"
        description="Hỏi đáp số liệu vận hành, tạo báo cáo trực quan và chuẩn bị thao tác để bạn xác nhận."
        :breadcrumbs="['Tổng quan' => route('admin.dashboard'), 'Trợ lý AI' => null]"
    />

    @if (! $aiEnabled)
        <div class="alert alert-warning" role="alert">
            <strong>Trợ lý AI chưa được bật.</strong>
            Cấu hình provider và đặt <code>AI_ENABLED=true</code> trước khi gửi câu hỏi.
        </div>
    @endif

    <div class="row g-3 ai-workspace">
        <aside class="col-lg-3">
            <section class="card ai-history-card">
                <div class="card-header d-flex align-items-center justify-content-between gap-2">
                    <h2 class="neo-display fs-6 mb-0">Hội thoại</h2>
                    <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.ai.index') }}">Mới</a>
                </div>
                <div class="list-group list-group-flush">
                    @forelse ($conversations as $item)
                        <a class="list-group-item list-group-item-action {{ $conversation?->is($item) ? 'active' : '' }}"
                           href="{{ route('admin.ai.index', ['conversation' => $item->id]) }}">
                            <span class="d-block fw-semibold text-truncate">{{ $item->title ?: 'Hội thoại mới' }}</span>
                            <small class="d-block opacity-75">{{ $item->last_message_at?->diffForHumans() }}</small>
                        </a>
                    @empty
                        <div class="p-3 small text-body-secondary">Chưa có hội thoại nào.</div>
                    @endforelse
                </div>
            </section>
        </aside>

        <div class="col-lg-9">
            <section class="card ai-chat-card">
                <div class="card-header d-flex align-items-center gap-2">
                    <span class="ai-avatar"><x-admin.icon name="sparkles" size="20" /></span>
                    <div>
                        <h2 class="neo-display fs-6 mb-0">Neo AI</h2>
                        <small class="text-body-secondary">Chỉ đọc dữ liệu trong phạm vi chi nhánh bạn được phép xem</small>
                    </div>
                </div>

                <div class="card-body ai-message-list" data-ai-message-list>
                    @if (! $conversation || $conversation->messages->isEmpty())
                        <div class="ai-welcome text-center mx-auto">
                            <span class="ai-avatar ai-avatar-lg"><x-admin.icon name="sparkles" size="30" /></span>
                            <h3 class="neo-display fs-5 mt-3">Bạn muốn xem gì hôm nay?</h3>
                            <p class="text-body-secondary">Ví dụ: “Tóm tắt tháng này”, “Vẽ biểu đồ doanh thu top dịch vụ”, hoặc “Đề xuất ghi khoản chi marketing 500.000đ hôm nay”.</p>
                        </div>
                    @else
                        @foreach ($conversation->messages as $message)
                            <article class="ai-message ai-message-{{ $message->role }}">
                                <div class="ai-message-meta">
                                    {{ $message->role === 'user' ? 'Bạn' : 'Neo AI' }}
                                    <time datetime="{{ $message->created_at->toIso8601String() }}">{{ $message->created_at->format('H:i d/m') }}</time>
                                </div>
                                <div class="ai-message-bubble">
                                    @if ($message->role === 'user' || empty($message->blocks))
                                        <p class="mb-0">{{ $message->content }}</p>
                                    @else
                                        @foreach ($message->blocks as $block)
                                            @if (($block['type'] ?? null) === 'text')
                                                <p class="mb-{{ $loop->last ? '0' : '3' }}">{{ $block['content'] ?? '' }}</p>
                                            @elseif (($block['type'] ?? null) === 'table')
                                                <div class="table-responsive mb-3">
                                                    <table class="table table-sm align-middle mb-0">
                                                        <thead><tr>
                                                            @foreach (($block['headers'] ?? []) as $header)<th scope="col">{{ $header }}</th>@endforeach
                                                        </tr></thead>
                                                        <tbody>
                                                            @foreach (($block['rows'] ?? []) as $row)
                                                                <tr>@foreach ($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                            @elseif (($block['type'] ?? null) === 'chart')
                                                <div class="ai-block-card mb-3">
                                                    @include('admin.ai.partials.chart', ['block' => $block])
                                                </div>
                                            @endif
                                        @endforeach
                                    @endif

                                    @foreach ($message->actionProposals as $proposal)
                                        <section class="ai-action mt-3">
                                            <div class="d-flex justify-content-between align-items-start gap-2">
                                                <div>
                                                    <span class="badge text-bg-light border mb-2">Đề xuất thao tác</span>
                                                    <p class="fw-semibold mb-1">{{ $proposal->summary }}</p>
                                                    <small class="text-body-secondary">Trạng thái: {{ $proposal->status->label() }}</small>
                                                </div>
                                                <x-admin.icon name="alert" size="20" class="text-warning flex-shrink-0" />
                                            </div>
                                            @if ($proposal->status === App\Enums\AiActionStatus::Pending)
                                                <div class="d-flex flex-wrap gap-2 mt-3">
                                                    <form method="POST" action="{{ route('admin.ai.actions.confirm', $proposal) }}">
                                                        @csrf
                                                        <button class="btn btn-sm btn-primary" type="submit">Xác nhận thực hiện</button>
                                                    </form>
                                                    <form method="POST" action="{{ route('admin.ai.actions.reject', $proposal) }}">
                                                        @csrf
                                                        <button class="btn btn-sm btn-outline-secondary" type="submit">Từ chối</button>
                                                    </form>
                                                </div>
                                            @endif
                                        </section>
                                    @endforeach
                                </div>
                            </article>
                        @endforeach
                    @endif
                </div>

                <div class="card-footer ai-composer">
                    <form method="POST" action="{{ route('admin.ai.messages.store') }}" data-ai-form>
                        @csrf
                        @if ($conversation)<input type="hidden" name="conversation_id" value="{{ $conversation->id }}">@endif
                        <label class="visually-hidden" for="ai-message">Câu hỏi cho trợ lý AI</label>
                        <textarea class="form-control @error('message') is-invalid @enderror" id="ai-message" name="message"
                                  rows="3" maxlength="{{ config('ai.max_message_length') }}"
                                  placeholder="Hỏi về doanh thu, lịch hẹn, dịch vụ, nhân viên hoặc tồn kho…"
                                  {{ $aiEnabled ? '' : 'disabled' }} required>{{ old('message') }}</textarea>
                        @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="d-flex justify-content-between align-items-center gap-2 mt-2">
                            <small class="text-body-secondary">Số liệu có thể được gửi tới nhà cung cấp AI đã cấu hình.</small>
                            <button class="btn btn-primary flex-shrink-0" type="submit" {{ $aiEnabled ? '' : 'disabled' }}>
                                <x-admin.icon name="sparkles" size="18" /> Gửi câu hỏi
                            </button>
                        </div>
                    </form>
                </div>
            </section>
        </div>
    </div>
</x-layouts.admin>
