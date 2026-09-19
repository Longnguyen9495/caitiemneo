{{-- Một tin nhắn trong hội thoại. Dùng chung cho lần tải trang đầu, cho phần
     phát trực tiếp và cho lúc vẽ lại sau khi duyệt đề xuất, để ba đường đi luôn
     cho ra cùng một giao diện. --}}
<article class="ai-message ai-message-{{ $message->role }}" data-ai-message="{{ $message->id }}">
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
                    <div class="table-responsive ai-table-scroll mb-3" role="region"
                         aria-label="Bảng dữ liệu từ Neo AI" tabindex="0">
                        <table class="table table-sm align-middle mb-0 ai-data-table">
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
            @php($actionDefinition = app(App\Services\Ai\AiActionCatalog::class)->definition($proposal->type))
            <section class="ai-action mt-3 {{ ($actionDefinition['destructive'] ?? false) ? 'ai-action-danger' : '' }}">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <div>
                        <div class="d-flex flex-wrap gap-2 mb-2">
                            <span class="badge text-bg-light border">
                                {{ $proposal->status === App\Enums\AiActionStatus::Pending ? 'Đề xuất chờ duyệt' : $proposal->status->label() }}
                            </span>
                            <span class="badge {{ ($actionDefinition['destructive'] ?? false) ? 'text-bg-danger' : 'text-bg-secondary' }}">
                                {{ $actionDefinition['label'] ?? $proposal->type }}
                            </span>
                        </div>
                        <p class="fw-semibold mb-1">{{ $proposal->summary }}</p>
                        <small class="text-body-secondary">Trạng thái: {{ $proposal->status->label() }}</small>
                    </div>
                    <x-admin.icon name="alert" size="20" class="{{ ($actionDefinition['destructive'] ?? false) ? 'text-danger' : 'text-warning' }} flex-shrink-0" />
                </div>

                <x-admin.ai-action-payload :proposal="$proposal" />

                @if ($proposal->failure_message)
                    <div class="alert alert-danger py-2 px-3 mt-3 mb-0 small">{{ $proposal->failure_message }}</div>
                @endif

                @if ($proposal->status === App\Enums\AiActionStatus::Executed && $proposal->result_id)
                    <p class="small text-success mt-3 mb-0">Đã thực hiện thành công · Mã kết quả #{{ $proposal->result_id }}</p>
                @endif

                @if ($proposal->status === App\Enums\AiActionStatus::Pending)
                    <p class="small text-body-secondary mt-3 mb-2">Chưa có dữ liệu nào bị thay đổi. Xác nhận sẽ yêu cầu phiên mật khẩu gần đây và được ghi audit log.</p>
                    <div class="d-flex flex-wrap gap-2">
                        <form method="POST" action="{{ route('admin.ai.actions.confirm', $proposal) }}" data-ai-action-form>
                            @csrf
                            <button class="btn btn-sm {{ ($actionDefinition['destructive'] ?? false) ? 'btn-danger' : 'btn-primary' }}" type="submit">
                                {{ ($actionDefinition['destructive'] ?? false) ? 'Xác nhận hủy' : 'Duyệt và thực hiện' }}
                            </button>
                        </form>
                        <form method="POST" action="{{ route('admin.ai.actions.reject', $proposal) }}" data-ai-action-form>
                            @csrf
                            <button class="btn btn-sm btn-outline-secondary" type="submit">Từ chối</button>
                        </form>
                    </div>
                @endif
            </section>
        @endforeach
    </div>
</article>
