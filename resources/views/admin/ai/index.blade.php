<x-layouts.admin title="Trợ lý AI" heading="Trợ lý AI">
    {{-- Trên điện thoại, thanh trên cùng đã ghi "Trợ lý AI"; bỏ tiêu đề trang để
         nhường toàn bộ chiều cao còn lại cho khung hội thoại. --}}
    <div class="d-none d-lg-block">
        <x-admin.page-header
            title="Trợ lý quản lý Cái Tiệm Neo"
            description="Hỏi số liệu vận hành, tạo báo cáo và chuẩn bị thao tác chờ bạn xác nhận."
            :breadcrumbs="['Tổng quan' => route('admin.dashboard'), 'Trợ lý AI' => null]"
        />
    </div>

    @if (! $aiEnabled)
        <div class="alert alert-warning py-2 small" role="alert">
            <strong>Trợ lý AI chưa được bật.</strong>
            Cấu hình provider và đặt <code>AI_ENABLED=true</code> trước khi gửi câu hỏi.
        </div>
    @endif

    <div class="ai-workspace">
        <section class="card ai-chat-card">
            <div class="ai-chat-layout">
                <aside class="ai-history-panel">
                    <div class="ai-history-heading d-none d-lg-flex align-items-center justify-content-between gap-2">
                        <div>
                            <h2 class="neo-display fs-6 mb-0">Hội thoại</h2>
                            <small class="text-body-secondary">30 cuộc gần nhất</small>
                        </div>
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.ai.index') }}">Mới</a>
                    </div>

                    {{-- Điện thoại: gộp tên trợ lý, chọn hội thoại và nút tạo mới vào
                         một hàng duy nhất thay cho ba khối xếp chồng. --}}
                    <div class="ai-mobile-bar d-lg-none">
                        <button class="ai-history-toggle" type="button"
                                data-bs-toggle="collapse" data-bs-target="#aiConversationHistory"
                                aria-expanded="false" aria-controls="aiConversationHistory">
                            <span class="ai-avatar ai-avatar-sm"><x-admin.icon name="sparkles" size="16" /></span>
                            <span class="text-truncate">{{ $conversation?->title ?: 'Hội thoại mới' }}</span>
                            <x-admin.icon name="chevron-down" size="18" class="ai-history-toggle-icon" />
                        </button>
                        <a class="btn btn-sm btn-outline-primary ai-new-chat" href="{{ route('admin.ai.index') }}"
                           aria-label="Hội thoại mới">
                            <x-admin.icon name="plus" size="18" />
                        </a>
                    </div>

                    <div class="collapse d-lg-block ai-history-list" id="aiConversationHistory">
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
                    </div>
                </aside>

                <div class="ai-chat-main">
                    <div class="ai-chat-header d-none d-lg-flex align-items-center gap-2">
                        <span class="ai-avatar"><x-admin.icon name="sparkles" size="20" /></span>
                        <div>
                            <h2 class="neo-display fs-6 mb-0">Neo AI</h2>
                            <small class="text-body-secondary">Chỉ đọc dữ liệu trong chi nhánh bạn được xem</small>
                        </div>
                    </div>

                    <div class="card-body ai-message-list" data-ai-message-list>
                        @if (! $conversation || $conversation->messages->isEmpty())
                            <div class="ai-welcome text-center mx-auto">
                                <span class="ai-avatar ai-avatar-lg"><x-admin.icon name="sparkles" size="30" /></span>
                                <h3 class="neo-display fs-5 mt-3 mb-3">Bạn muốn xem gì hôm nay?</h3>
                                <div class="ai-suggestions">
                                    @foreach (['Tóm tắt tháng này', 'Biểu đồ doanh thu theo dịch vụ', 'Lịch hẹn hôm nay'] as $suggestion)
                                        <button class="ai-suggestion" type="button" data-ai-suggestion>{{ $suggestion }}</button>
                                    @endforeach
                                </div>
                            </div>
                        @else
                            @foreach ($conversation->messages as $message)
                                @include('admin.ai.partials.message', ['message' => $message])
                            @endforeach
                        @endif
                    </div>

                    <div class="card-footer ai-composer">
                        <form method="POST" action="{{ route('admin.ai.messages.store') }}" data-ai-form
                              data-ai-stream-url="{{ route('admin.ai.messages.stream') }}">
                            @csrf
                            @if ($conversation)<input type="hidden" name="conversation_id" value="{{ $conversation->id }}">@endif
                            <label class="visually-hidden" for="ai-message">Câu hỏi cho trợ lý AI</label>
                            <div class="ai-composer-row">
                                <div class="flex-grow-1" style="min-width:0">
                                    <textarea class="form-control @error('message') is-invalid @enderror" id="ai-message" name="message"
                                              rows="1" maxlength="{{ config('ai.max_message_length') }}"
                                              placeholder="Hỏi về doanh thu, lịch hẹn, tồn kho…"
                                              {{ $aiEnabled ? '' : 'disabled' }} required>{{ old('message') }}</textarea>
                                    @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <button class="btn btn-primary ai-send" type="submit" aria-label="Gửi câu hỏi"
                                        {{ $aiEnabled ? '' : 'disabled' }}>
                                    <x-admin.icon name="send" size="20" />
                                    <span class="ai-send-label d-none d-lg-inline" data-ai-submit-label>Gửi câu hỏi</span>
                                </button>
                                {{-- Lối thoát khi câu trả lời lâu hơn mong đợi. Không có nút này
                                     người dùng chỉ còn cách tải lại trang và mất luôn ngữ cảnh. --}}
                                <button class="btn btn-outline-secondary ai-stop d-none" type="button"
                                        data-ai-stop aria-label="Dừng trả lời">
                                    <x-admin.icon name="alert" size="20" />
                                    <span class="ai-send-label d-none d-lg-inline">Dừng</span>
                                </button>
                            </div>
                            <small class="text-body-secondary d-none d-lg-block mt-2">Số liệu có thể được gửi tới nhà cung cấp AI đã cấu hình.</small>
                        </form>
                    </div>
                </div>
            </div>
        </section>
    </div>
</x-layouts.admin>
