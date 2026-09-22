<x-layouts.admin title="Album trang chủ" heading="Album trang chủ">
    <x-admin.page-header
        title="Album trang chủ"
        :description="$album->hint()" />

    {{-- Chuyển khu bằng hai thẻ: người đăng luôn thấy mình đang đứng ở khu nào
         trước khi chọn tệp, nên không còn cảnh ảnh tin nhắn lọt vào lưới mẫu. --}}
    <nav class="nav nav-pills gap-2 mb-3" aria-label="Chọn khu hiển thị">
        @foreach ($albums as $option)
            <a class="nav-link @if ($option === $album) active @endif"
               href="{{ route('admin.gallery.index', ['album' => $option->value]) }}"
               @if ($option === $album) aria-current="page" @endif>
                {{ $option->label() }}
                <span class="badge rounded-pill text-bg-light ms-1 neo-num">{{ $counts[$option->value] ?? 0 }}</span>
            </a>
        @endforeach
    </nav>

    @can('create', App\Models\GalleryItem::class)
        <section class="card mb-3">
            <form class="card-body row g-3 align-items-end" method="POST" action="{{ route('admin.gallery.store') }}" enctype="multipart/form-data" data-gallery-upload-form>
                @csrf
                {{-- Tải lên đúng khu đang xem. Giá trị đi theo biểu mẫu chứ không
                     suy ra ở máy chủ, nên đổi thẻ là đổi cả đích đến của tệp. --}}
                <input type="hidden" name="album" value="{{ $album->value }}">

                <div class="col-12 col-lg-8">
                    <label class="form-label" for="media">Chọn ảnh hoặc video cho khu <strong>{{ $album->label() }}</strong></label>
                    <input
                        class="form-control @error('media.*') is-invalid @enderror"
                        id="media"
                        type="file"
                        name="media[]"
                        accept="image/jpeg,image/png,image/webp,video/mp4,video/quicktime,video/webm"
                        multiple
                        required
                        data-gallery-media>
                    <small class="d-block mt-1 text-body-secondary">
                        Tối đa {{ $maxFiles }} tệp mỗi lần. Ảnh JPG, PNG, WebP dung lượng lớn sẽ tự tối ưu trước khi gửi.
                        Video giữ nguyên và tối đa {{ $maxMegabytes }} MB. Ảnh HEIC của iPhone cần xuất sang JPG trước.
                    </small>
                    <div class="form-text" data-gallery-upload-status hidden aria-live="polite"></div>
                    <div class="invalid-feedback d-block" data-gallery-upload-error hidden></div>
                </div>

                <div class="col-12 col-lg-4 d-flex justify-content-lg-end">
                    <x-admin.submit-button :label="'Đăng vào '.$album->label()" />
                </div>
            </form>
        </section>
    @endcan

    <section class="card overflow-hidden">
        <div class="card-body">
            <div class="row g-3">
                @forelse ($items as $item)
                    <div class="col-6 col-md-4 col-xl-3">
                        <figure class="neo-media-tile mb-0">
                            @if ($item->isPhoto())
                                <img
                                    class="neo-media-tile__preview"
                                    src="{{ $item->thumbnailUrl() }}"
                                    alt="{{ $item->original_name }}"
                                    loading="lazy"
                                    decoding="async">
                            @else
                                {{-- preload="metadata" để trình duyệt vẽ khung hình đầu làm ảnh đại diện. --}}
                                <video class="neo-media-tile__preview" src="{{ $item->url() }}" preload="metadata" muted playsinline></video>
                            @endif

                            <figcaption class="neo-media-tile__meta">
                                <span class="d-flex align-items-center gap-1">
                                    <x-admin.status-badge :tone="$item->isPhoto() ? 'is-active' : 'is-warning'" :label="$item->type->label()" />
                                </span>
                                <strong class="d-block text-truncate" title="{{ $item->original_name }}">{{ $item->original_name }}</strong>
                                <small class="d-block text-body-secondary">
                                    {{ $item->created_at->timezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i') }}
                                    · {{ number_format($item->byte_size / 1048576, 1) }} MB
                                </small>
                                @can('delete', $item)
                                    <div class="mt-2">
                                        <x-admin.confirm-form
                                            :action="route('admin.gallery.destroy', $item)"
                                            method="DELETE"
                                            label="Gỡ khỏi album"
                                            :message="'Gỡ ' . $item->original_name . ' khỏi trang công khai?'" />
                                    </div>
                                @endcan
                            </figcaption>
                        </figure>
                    </div>
                @empty
                    <div class="col-12">
                        <x-admin.empty-state
                            icon="image"
                            :title="'Khu '.$album->label().' chưa có gì'"
                            :hint="$album->hint()" />
                    </div>
                @endforelse
            </div>
        </div>

        <x-admin.pagination :paginator="$items" />
    </section>
</x-layouts.admin>
