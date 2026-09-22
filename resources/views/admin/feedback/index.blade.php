<x-layouts.admin title="Feedback khách viết" heading="Feedback khách viết">
    <x-admin.page-header
        title="Feedback khách viết"
        :description="$pendingCount > 0
            ? 'Có '.$pendingCount.' feedback đang chờ duyệt. Duyệt xong mới hiện trên trang chủ.'
            : 'Lời khen khách tự gõ ở trang chủ. Chỉ feedback được duyệt mới hiện ra ngoài. Ảnh và video feedback thì đăng ở mục Album trang chủ → thẻ Feedback ảnh/video.'" />

    <section class="card overflow-hidden">
        <x-admin.filter-bar :action="route('admin.feedback.index')" submit-label="Xem">
            <div class="col-12 col-lg-4">
                <label class="form-label" for="status">Trạng thái</label>
                <select class="form-select" id="status" name="status">
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected($selectedStatus === $value)>{{ $label }}</option>
                    @endforeach
                    <option value="all" @selected($selectedStatus === 'all')>Tất cả</option>
                </select>
            </div>
        </x-admin.filter-bar>

        <ul class="list-unstyled mb-0">
            @forelse ($items as $item)
                <li class="border-bottom p-3">
                    <div class="d-flex align-items-start justify-content-between flex-wrap gap-2">
                        <div class="min-w-0">
                            <p class="mb-1 fw-semibold text-truncate">{{ $item->author_name }}</p>
                            {{-- Số sao viết bằng ký tự để đọc được cả khi in ra giấy hay dán vào Zalo. --}}
                            <p class="mb-0 small" aria-label="{{ $item->rating }} trên 5 sao">
                                <span class="text-primary-emphasis">{{ str_repeat('★', $item->rating) }}</span><span class="text-body-tertiary">{{ str_repeat('★', 5 - $item->rating) }}</span>
                                <span class="text-body-secondary ms-1 neo-num">{{ $item->rating }}/5</span>
                            </p>
                        </div>
                        <x-admin.status-badge :status="$item->status" />
                    </div>

                    <p class="mb-2 mt-2">{{ $item->content }}</p>

                    <small class="d-block text-body-secondary">
                        Khách gửi {{ $item->created_at->timezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i') }}
                        @if ($item->reviewer)
                            · {{ $item->reviewer->name }} xử lý
                        @endif
                        @if ($item->published_at)
                            · đăng {{ $item->published_at->timezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i') }}
                        @endif
                    </small>

                    @can('moderate', $item)
                        <div class="neo-actions mt-3">
                            @unless ($item->isPublished())
                                <form method="POST" action="{{ route('admin.feedback.update', $item) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="published">
                                    <x-admin.submit-button label="Cho lên trang chủ" variant="primary btn-sm" />
                                </form>
                            @else
                                <form method="POST" action="{{ route('admin.feedback.update', $item) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="rejected">
                                    <x-admin.submit-button label="Gỡ khỏi trang chủ" variant="outline-secondary btn-sm" />
                                </form>
                            @endunless

                            <x-admin.confirm-form
                                :action="route('admin.feedback.destroy', $item)"
                                method="DELETE"
                                label="Xóa hẳn"
                                :message="'Xóa feedback của '.$item->author_name.'? Thao tác này không lấy lại được.'" />
                        </div>
                    @endcan
                </li>
            @empty
                <li>
                    <x-admin.empty-state
                        icon="send"
                        title="Không có feedback nào ở trạng thái này"
                        hint="Khách gửi feedback từ khu 'Khách nói gì' trên trang chủ." />
                </li>
            @endforelse
        </ul>

        <x-admin.pagination :paginator="$items" />
    </section>
</x-layouts.admin>
