<!doctype html>
<html lang="vi">
  <head>
    <x-public.head
      title="Mẫu móng đã làm | Cái Tiệm Neo"
      description="Album mẫu móng làm thật tại Cái Tiệm Neo — 47 ngõ 131 Thái Hà, Hà Nội. Chọn mẫu bạn thích và đặt lịch ngay trên trang."
      share-title="Mẫu móng tại Cái Tiệm Neo"
      share-description="Xem album mẫu móng làm thật tại tiệm, ưng mẫu nào đặt lịch ngay mẫu đó."
      :preload-photo="$photos->first()"
      preload-sizes="(max-width: 760px) 46vw, 300px"
    />
  </head>
  <body>
    <a class="skip-link" href="#album">Đi đến album</a>
    <x-public.header booking-dialog />
    <main id="album">
      <section class="lookbook-intro section-shell" aria-labelledby="lookbook-title">
        <div data-reveal>
          <p class="eyebrow"><span aria-hidden="true">✦</span> Album mẫu móng</p>
          <h1 id="lookbook-title">Ưng mẫu nào,<br /><em>đặt luôn mẫu đó.</em></h1>
          <p>Toàn bộ ảnh dưới đây chụp tại tiệm sau khi khách rời ghế. Chạm vào một mẫu để mở ô đặt lịch — tiệm sẽ biết bạn muốn làm đúng mẫu ấy.</p>
        </div>
        <p class="lookbook-count" data-reveal data-reveal-delay><b>{{ $photos->total() }}</b> mẫu trong album</p>
      </section>

      @if ($photos->isNotEmpty())
        <section class="lookbook-grid-section" aria-label="Lưới mẫu móng">
          <ul class="lookbook-grid section-shell">
            @foreach ($photos as $photo)
              @php($number = $photos->firstItem() + $loop->index)
              <li>
                <button class="lookbook-card" type="button"
                        data-lookbook-open
                        data-photo-id="{{ $photo->id }}"
                        data-photo-number="{{ $number }}"
                        aria-label="Đặt lịch làm mẫu móng số {{ $number }}">
                  <img src="{{ $photo->url() }}" srcset="{{ $photo->srcset() }}" sizes="(max-width: 760px) 46vw, 300px" width="{{ $photo->width }}" height="{{ $photo->height }}" alt="Mẫu móng số {{ $number }} do Cái Tiệm Neo thực hiện" loading="{{ $loop->index < 4 ? 'eager' : 'lazy' }}" decoding="async" />
                  <span class="lookbook-card__index" aria-hidden="true">{{ str_pad((string) $number, 2, '0', STR_PAD_LEFT) }}</span>
                  <span class="lookbook-card__cta" aria-hidden="true">Đặt mẫu này <span>↗</span></span>
                </button>
              </li>
            @endforeach
          </ul>

          @if ($photos->hasPages())
            <nav class="lookbook-pager section-shell" aria-label="Chuyển trang album">
              @if ($photos->onFirstPage())
                <span class="lookbook-pager__spacer" aria-hidden="true"></span>
              @else
                <a class="button button-secondary" href="{{ $photos->previousPageUrl() }}" rel="prev"><span aria-hidden="true">←</span> Mẫu mới hơn</a>
              @endif
              <p>Trang {{ $photos->currentPage() }} / {{ $photos->lastPage() }}</p>
              @if ($photos->hasMorePages())
                <a class="button button-secondary" href="{{ $photos->nextPageUrl() }}" rel="next">Mẫu cũ hơn <span aria-hidden="true">→</span></a>
              @else
                <span class="lookbook-pager__spacer" aria-hidden="true"></span>
              @endif
            </nav>
          @endif
        </section>
      @else
        <section class="lookbook-empty section-shell">
          <p class="empty-note">Album đang được cập nhật. Bạn gọi <a href="tel:0826881094">0826 881 094</a> để tiệm gửi mẫu mới nhất nhé.</p>
        </section>
      @endif
    </main>
    <x-public.footer />
    <x-public.quick-actions booking-dialog />

    {{--
      Hộp đặt lịch bật lên từ một tấm ảnh.

      Chỉ có đúng một biểu mẫu trong trang: mỗi thẻ ảnh chỉ đổi phần xem trước
      và mã mẫu, nên album dài bao nhiêu cũng không làm trang nặng thêm.
      `data-booking-reopen` là lúc biểu mẫu bị trả về vì thiếu thông tin —
      hộp phải mở lại ngay với đúng những gì khách đã điền.
    --}}
    <dialog class="booking-dialog" data-booking-dialog @if ($errors->any()) data-booking-reopen @endif aria-labelledby="booking-dialog-title">
      <div class="booking-dialog__inner">
        <button class="booking-dialog__close" type="button" data-booking-dialog-close aria-label="Đóng ô đặt lịch"><span aria-hidden="true">×</span></button>
        <figure class="booking-dialog__photo" data-booking-photo @unless ($selectedPhoto) hidden @endunless>
          <img data-booking-photo-image
               @if ($selectedPhoto) src="{{ $selectedPhoto->url() }}" srcset="{{ $selectedPhoto->srcset() }}" @endif
               sizes="(max-width: 860px) 60vw, 15rem"
               width="{{ $selectedPhoto?->width ?? 1440 }}" height="{{ $selectedPhoto?->height ?? 1920 }}"
               alt="Mẫu móng bạn đang chọn" decoding="async" />
          <figcaption>Mẫu bạn chọn<span data-booking-photo-label></span></figcaption>
        </figure>
        <div class="booking-dialog__form">
          <p class="eyebrow">Đặt lịch online</p>
          <h2 id="booking-dialog-title">Giữ chỗ cho <em>đôi tay của bạn.</em></h2>
          <p class="booking-dialog__note">Tiệm nhận yêu cầu rồi gọi lại xác nhận giờ. Bạn cũng có thể gọi thẳng <a href="tel:0826881094">0826 881 094</a>.</p>
          <x-public.booking-form
            :branches="$branches"
            :service-groups="$serviceGroups"
            source="lookbook"
            :selected-photo="$selectedPhoto"
          />
        </div>
      </div>
    </dialog>

    <x-public.booking-success />
  </body>
</html>
