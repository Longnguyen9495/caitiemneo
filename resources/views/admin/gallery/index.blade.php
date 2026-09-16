<x-layouts.admin title="Album trang chủ" heading="Album trang chủ">
    <x-admin.page-header
        title="Album trang chủ"
        description="Ảnh và video hiển thị trên trang công khai. Tệp đăng sau luôn đứng trước." />

    @can('create', App\Models\GalleryItem::class)
        <section class="card mb-3">
            <form class="card-body row g-3 align-items-end" method="POST" action="{{ route('admin.gallery.store') }}" enctype="multipart/form-data">
                @csrf

                <div class="col-12 col-lg-8">
                    <label class="form-label" for="media">Chọn ảnh hoặc video</label>
                    <input
                        class="form-control @error('media.*') is-invalid @enderror"
                        id="media"
                        type="file"
                        name="media[]"
                        accept="image/jpeg,image/png,image/webp,video/mp4,video/quicktime,video/webm"
                        multiple
                        required>
                    <small class="d-block mt-1 text-body-secondary">
                        Tối đa {{ $maxFiles }} tệp mỗi lần, mỗi tệp {{ $maxMegabytes }} MB.
                        Ảnh sẽ được nén sẵn nhiều khổ; video giữ nguyên nên hãy quay ngắn.
                        Ảnh HEIC của iPhone cần xuất sang JPG trước.
                    </small>
                </div>

                <div class="col-12 col-lg-4 d-flex justify-content-lg-end">
                    <x-admin.submit-button label="Đăng lên album" />
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
                            title="Album chưa có gì"
                            hint="Đăng ảnh mẫu móng hoặc video ngắn để trang công khai có cái trưng." />
                    </div>
                @endforelse
            </div>
        </div>

        <x-admin.pagination :paginator="$items" />
    </section>
</x-layouts.admin>
