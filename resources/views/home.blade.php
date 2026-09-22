<!doctype html>
@php
    // Album là nguồn ảnh duy nhất của trang, nên hero và khu kể chuyện cũng lấy
    // ảnh từ đó theo vị trí — thêm bớt ảnh trong thư mục không làm vỡ bố cục.
    $heroPhoto = $gallery->first();
    $heroAccent = $gallery->get(2) ?? $gallery->get(1);
    $storyPhoto = $gallery->get(6) ?? $gallery->get(1);
@endphp
<html lang="vi">
  <head>
    <x-public.head
      title="Cái Tiệm Neo | Nail có gu ở Thái Hà"
      description="Cái Tiệm Neo — tiệm nail tại 47 ngõ 131 Thái Hà, Đống Đa, Hà Nội. Xem mẫu móng, bảng giá và đặt lịch online."
      share-description="Xem mẫu móng thật tại tiệm, bảng giá rõ ràng và đặt lịch online trong một phút."
      :preload-photo="$heroPhoto"
    />
  </head>
  <body>
    <a class="skip-link" href="#noi-dung">Đi đến nội dung chính</a>
    <x-public.header />
    <main id="noi-dung">
      <section id="dau-trang" class="hero section-shell" aria-labelledby="hero-title">
        <div class="hero-copy" data-reveal>
          <p class="eyebrow"><span aria-hidden="true">✦</span> Nail studio · Thái Hà, Hà Nội</p>
          <h1 id="hero-title">Một nhịp chậm<br /><em>cho đôi tay có gu.</em></h1>
          <p class="hero-text">Cái Tiệm Neo là một góc nhỏ để bạn chọn màu mình thích, mang theo ý tưởng riêng và dành thời gian cho bản thân.</p>
          <div class="hero-actions"><a class="button button-primary" href="#dat-lich">Đặt lịch online <span aria-hidden="true">↗</span></a><a class="button button-secondary" href="#mau-mong">Xem mẫu móng <span aria-hidden="true">↓</span></a></div>
          <p class="hero-meta"><span aria-hidden="true">⌖</span> 47 ngõ 131 Thái Hà, Đống Đa, Hà Nội</p>
        </div>
        <div class="hero-visual" data-reveal data-reveal-delay>
          @if ($heroPhoto)
            <span class="hero-arch" aria-hidden="true"></span>
            <figure class="hero-photo">
              <img src="{{ $heroPhoto->url() }}" srcset="{{ $heroPhoto->srcset() }}" sizes="(max-width: 760px) 88vw, 30vw" width="{{ $heroPhoto->width }}" height="{{ $heroPhoto->height }}" alt="Bộ móng ánh nhũ pha xanh tím thực hiện tại Cái Tiệm Neo" fetchpriority="high" decoding="async" />
            </figure>
            @if ($heroAccent)
              <figure class="hero-photo hero-photo-accent">
                <img src="{{ $heroAccent->url() }}" srcset="{{ $heroAccent->srcset() }}" sizes="(max-width: 760px) 38vw, 14vw" width="{{ $heroAccent->width }}" height="{{ $heroAccent->height }}" alt="Bộ móng dáng dài màu sữa đính nơ thực hiện tại Cái Tiệm Neo" loading="lazy" decoding="async" />
              </figure>
            @endif
            <p class="hero-badge"><b>{{ $gallery->count() }}</b> mẫu<br />trong album</p>
          @endif
        </div>
      </section>

      <section class="facts-section" aria-label="Thông tin nhanh về tiệm">
        <ul class="facts-rail section-shell">
          <li><span aria-hidden="true">✦</span> {{ $branches->count() }} cơ sở tại Hà Nội</li>
          <li><span aria-hidden="true">✦</span> {{ $services->count() }} dịch vụ trên bảng giá</li>
          <li><span aria-hidden="true">✦</span> Đặt lịch trước tối thiểu 1 giờ</li>
          <li><span aria-hidden="true">✦</span> Hotline <a href="tel:0826881094">0826 881 094</a></li>
        </ul>
      </section>

      @if ($gallery->isNotEmpty())
        <section id="mau-mong" class="gallery-section" aria-labelledby="gallery-title">
          <div class="section-shell gallery-heading" data-reveal>
            <p class="eyebrow">Album của tiệm</p>
            <h2 id="gallery-title">Mẫu móng <em>làm thật</em><br />tại Cái Tiệm Neo.</h2>
            <p>Ảnh chụp ngay tại tiệm sau khi khách rời ghế.</p>
            <a class="gallery-all-link" href="{{ route('lookbook') }}">Xem cả {{ $photoCount }} mẫu và đặt lịch <span aria-hidden="true">↗</span></a>
            <div class="gallery-controls">
              <button class="gallery-arrow" type="button" data-gallery-prev aria-label="Xem những mẫu trước"><span aria-hidden="true">←</span></button>
              <button class="gallery-arrow" type="button" data-gallery-next aria-label="Xem thêm mẫu"><span aria-hidden="true">→</span></button>
            </div>
          </div>
          <ul class="gallery-rail" data-gallery-rail data-lightbox-group>
            @foreach ($gallery as $index => $photo)
              <li>
                <button class="gallery-item" type="button" data-lightbox-open aria-label="Phóng to mẫu móng số {{ $index + 1 }}">
                  <img src="{{ $photo->url() }}" srcset="{{ $photo->srcset() }}" sizes="(max-width: 760px) 68vw, 300px" width="{{ $photo->width }}" height="{{ $photo->height }}" alt="Mẫu móng số {{ $index + 1 }} do Cái Tiệm Neo thực hiện" loading="lazy" decoding="async" />
                  <span class="gallery-item-index" aria-hidden="true">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                </button>
              </li>
            @endforeach
          </ul>
          <p class="gallery-hint section-shell"><span aria-hidden="true">↔</span> Vuốt ngang để xem {{ $gallery->count() }} mẫu mới nhất, chạm vào ảnh để phóng to.</p>
        </section>
      @endif

      @if ($videos->isNotEmpty())
        <section id="video" class="reel-section section-shell" aria-labelledby="reel-title">
          <div class="reel-heading" data-reveal>
            <p class="eyebrow">Video tại tiệm</p>
            <h2 id="reel-title">Xem tận mắt<br /><em>lúc đang làm.</em></h2>
            <p>Vài đoạn ngắn quay ngay trên ghế, không dựng lại.</p>
          </div>
          <ul class="reel-rail" data-reveal data-reveal-delay>
            @foreach ($videos as $video)
              <li>
                {{-- preload="metadata" để trình duyệt vẽ sẵn khung hình đầu làm ảnh
                     đại diện, thay cho một ô đen. Không tự phát: trang quảng cáo mà
                     tự kêu trong túi khách là mất thiện cảm ngay. --}}
                <video controls preload="metadata" playsinline aria-label="Video số {{ $loop->iteration }} quay tại Cái Tiệm Neo">
                  <source src="{{ $video->url() }}" />
                  Trình duyệt của bạn chưa phát được video này.
                </video>
              </li>
            @endforeach
          </ul>
        </section>
      @endif

      <section id="bang-gia" class="price-section section-shell" aria-labelledby="price-title">
        <div class="price-intro" data-reveal>
          <p class="eyebrow">Bảng giá</p>
          <h2 id="price-title">Giá rõ ràng,<br /><em>nói trước khi làm.</em></h2>
          <p>Mức giá dưới đây áp dụng cho dịch vụ tiêu chuẩn. Mẫu vẽ cầu kỳ hoặc móng cần xử lý thêm sẽ được báo trước khi bắt đầu.</p>
          <a class="button button-secondary" href="#dat-lich">Chọn dịch vụ và đặt lịch <span aria-hidden="true">↗</span></a>
        </div>
        <div class="price-groups" data-reveal data-reveal-delay>
          @forelse ($serviceGroups as $label => $rows)
            <article class="price-group">
              <h3>{{ $label }}</h3>
              <ul>
                @foreach ($rows as $service)
                  <li><span>{{ $service->name }}</span><b>{{ $service->formatted_price }}</b></li>
                @endforeach
              </ul>
            </article>
          @empty
            <p class="empty-note">Bảng giá đang được cập nhật, bạn gọi <a href="tel:0826881094">0826 881 094</a> để tiệm báo giá nhé.</p>
          @endforelse
        </div>
      </section>

      <section id="gioi-thieu" class="story-section section-shell" aria-labelledby="story-title">
        <div class="story-art" data-reveal>
          @if ($storyPhoto)
            <figure class="story-photo">
              <img src="{{ $storyPhoto->url() }}" srcset="{{ $storyPhoto->srcset() }}" sizes="(max-width: 760px) 74vw, 28vw" width="{{ $storyPhoto->width }}" height="{{ $storyPhoto->height }}" alt="Một bộ móng vừa hoàn thiện tại Cái Tiệm Neo" loading="lazy" decoding="async" />
            </figure>
          @endif
          <div class="story-stamp" aria-hidden="true"><span>made<br />with<br />care</span></div>
        </div>
        <div class="story-copy" data-reveal data-reveal-delay>
          <p class="eyebrow">Về Cái Tiệm Neo</p>
          <h2 id="story-title">Neo lại một chút,<br />rồi đi thật <em>đẹp.</em></h2>
          <p class="lead">Ở đây, một cuộc hẹn có thể bắt đầu từ gam màu bạn thích hoặc một điều nho nhỏ bạn muốn thử.</p>
          <p>Giữa nhịp phố Thái Hà, Cái Tiệm Neo là một góc nhỏ dành cho những phút bạn muốn ngồi xuống và để đôi tay được chăm chút. Mỗi lần ghé tiệm có thể bắt đầu từ một ý tưởng bạn mang theo, hoặc đơn giản là mong muốn dành thời gian cho chính mình.</p>
          <div class="inline-links" aria-label="Kênh liên hệ">
            <a class="arrow-link" href="tel:0826881094">Gọi 0826 881 094 <span aria-hidden="true">↗</span></a>
            <a class="arrow-link" href="https://www.instagram.com/caitiemneo/" target="_blank" rel="noopener noreferrer">Theo dõi @caitiemneo <span aria-hidden="true">↗</span></a>
          </div>
        </div>
      </section>

      <section id="feedback" class="feedback-section section-shell" aria-labelledby="feedback-title">
        <div class="feedback-heading" data-reveal>
          <p class="eyebrow">Khách nói gì</p>
          <h2 id="feedback-title">Feedback từ người<br /><em>đã ngồi ghế ở tiệm.</em></h2>
          @if ($feedbackSummary['average'] !== null)
            <p class="feedback-score">
              <b>{{ number_format($feedbackSummary['average'], 1) }}</b>
              <x-public.stars :rating="round($feedbackSummary['average'])" />
              <span>{{ $feedbackSummary['total'] }} lượt đánh giá</span>
            </p>
          @endif
        </div>

        @if (session('feedback_success'))
          <div class="booking-alert booking-success feedback-notice" role="status">{{ session('feedback_success') }}</div>
        @endif
        @if ($errors->feedback->any())
          <div class="booking-alert feedback-notice" role="alert">
            <p>Feedback chưa gửi được, bạn xem lại giúp tiệm:</p>
            <ul>
              @foreach ($errors->feedback->all() as $error)
                <li>{{ $error }}</li>
              @endforeach
            </ul>
          </div>
        @endif

        {{-- Feedback thật của khách: ảnh chụp tin nhắn và video khách review,
             do tiệm tải lên trong khu quản trị. Đây là phần đáng tin nhất của
             khu này nên nó đứng trước mọi lời chữ. --}}
        @if ($feedbackPhotos->isNotEmpty() || $feedbackVideos->isNotEmpty())
          <ul class="feedback-wall" data-reveal data-lightbox-group>
            @foreach ($feedbackPhotos as $photo)
              <li>
                <button class="feedback-shot" type="button" data-lightbox-open aria-label="Xem to feedback số {{ $loop->iteration }} của khách">
                  <img src="{{ $photo->url() }}" srcset="{{ $photo->srcset() }}" sizes="(max-width: 760px) 62vw, 230px" width="{{ $photo->width }}" height="{{ $photo->height }}" alt="Lời khen khách gửi cho Cái Tiệm Neo, ảnh số {{ $loop->iteration }}" loading="lazy" decoding="async" />
                  <span class="feedback-shot__zoom" aria-hidden="true">Xem to</span>
                </button>
              </li>
            @endforeach
            @foreach ($feedbackVideos as $video)
              <li>
                {{-- Không tự phát: một trang quảng cáo tự kêu trong túi khách là
                     mất thiện cảm ngay. --}}
                <video class="feedback-clip" controls preload="metadata" playsinline aria-label="Video khách review số {{ $loop->iteration }}">
                  <source src="{{ $video->url() }}" />
                  Trình duyệt của bạn chưa phát được video này.
                </video>
              </li>
            @endforeach
          </ul>
        @endif

        @if ($feedback->isNotEmpty())
          <ul class="feedback-rail" data-reveal data-reveal-delay>
            @foreach ($feedback as $item)
              <li>
                <article class="feedback-card">
                  <x-public.stars :rating="$item->rating" />
                  <blockquote>{{ $item->content }}</blockquote>
                  <footer>
                    <span class="feedback-card__name">{{ $item->author_name }}</span>
                    <span>{{ $item->published_at?->format('m/Y') }}</span>
                  </footer>
                </article>
              </li>
            @endforeach
          </ul>
        @elseif ($feedbackPhotos->isEmpty() && $feedbackVideos->isEmpty())
          <p class="empty-note">Tiệm chưa đăng feedback nào — bạn là người đầu tiên nhé.</p>
        @endif

        {{-- <details> chứ không phải hộp bật lên: mở được khi không có
             JavaScript, và khu này không cần dài sẵn trên điện thoại. Gửi sai
             thì mở lại kèm những gì khách đã gõ. --}}
        <details class="feedback-compose" @if ($errors->feedback->any()) open @endif>
          <summary>
            <span>Viết feedback cho tiệm</span>
            <span class="feedback-compose__sign" aria-hidden="true">+</span>
          </summary>
          <x-public.feedback-form />
        </details>
      </section>

      <section id="lien-he" class="visit-section" aria-labelledby="visit-title">
        <div class="section-shell visit-layout">
          <div class="visit-copy" data-reveal>
            <p class="eyebrow">Hẹn bạn ở tiệm</p>
            <h2 id="visit-title">Đôi tay của bạn<br />xứng đáng một <em>cuộc hẹn.</em></h2>
            <p>Gọi trước để tiệm giữ chỗ và chuẩn bị mẫu bạn muốn làm.</p>
            <a class="button button-primary" href="tel:0826881094">Gọi 0826 881 094 <span aria-hidden="true">↗</span></a>
          </div>
          <address class="address-card" data-reveal data-reveal-delay>
            <span class="address-icon" aria-hidden="true">⌖</span>
            <p>47 ngõ 131 Thái Hà<br />Đống Đa, Hà Nội</p>
            <a class="arrow-link" href="https://www.google.com/maps/search/?api=1&query=47%20ng%C3%B5%20131%20Th%C3%A1i%20H%C3%A0%2C%20%C4%90%E1%BB%91ng%20%C4%90a%2C%20H%C3%A0%20N%E1%BB%99i" target="_blank" rel="noopener noreferrer">Mở bản đồ <span aria-hidden="true">↗</span></a>
          </address>
        </div>
      </section>

      <section id="dat-lich" class="booking-section section-shell" aria-labelledby="booking-title">
        <div class="booking-copy" data-reveal>
          <p class="eyebrow">Đặt lịch online</p>
          <h2 id="booking-title">Hẹn một khoảng<br /><em>thời gian cho bạn.</em></h2>
          <p>Điền thông tin cơ bản, Tiệm Neo sẽ kiểm tra lịch và liên hệ xác nhận với bạn sớm nhất.</p>
          <p class="booking-note">Để được tư vấn nhanh hơn, bạn cũng có thể gọi <a href="tel:0826881094">0826 881 094</a>.</p>
        </div>
        <div class="booking-card" data-reveal data-reveal-delay>
          <x-public.booking-form :branches="$branches" :service-groups="$serviceGroups" source="home" />
        </div>
      </section>

      <x-public.booking-success />
    </main>
    <x-public.footer />

    <x-public.quick-actions />

    @if ($gallery->isNotEmpty() || $feedbackPhotos->isNotEmpty())
      <dialog class="lightbox" data-lightbox aria-label="Ảnh phóng to">
        <button class="lightbox-close" type="button" data-lightbox-close aria-label="Đóng ảnh"><span aria-hidden="true">×</span></button>
        <figure class="lightbox-figure">
          <img data-lightbox-image src="" alt="" width="1440" height="1920" />
        </figure>
        <div class="lightbox-bar">
          <button type="button" data-lightbox-prev aria-label="Ảnh trước"><span aria-hidden="true">←</span></button>
          <p data-lightbox-counter aria-live="polite"></p>
          <button type="button" data-lightbox-next aria-label="Ảnh sau"><span aria-hidden="true">→</span></button>
        </div>
      </dialog>
    @endif
  </body>
</html>
