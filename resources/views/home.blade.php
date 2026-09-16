<!doctype html>
@php
    // Album là nguồn ảnh duy nhất của trang, nên hero và khu kể chuyện cũng lấy
    // ảnh từ đó theo vị trí — thêm bớt ảnh trong thư mục không làm vỡ bố cục.
    $heroPhoto = $gallery->first();
    $heroAccent = $gallery->get(2) ?? $gallery->get(1);
    $storyPhoto = $gallery->get(6) ?? $gallery->get(1);
    $shareImage = $heroPhoto?->url() ?? asset('images/logo-neo.png');
@endphp
<html lang="vi">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="description" content="Cái Tiệm Neo — tiệm nail tại 47 ngõ 131 Thái Hà, Đống Đa, Hà Nội. Xem mẫu móng, bảng giá và đặt lịch online." />
    <meta name="theme-color" content="#51212b" />
    <meta name="color-scheme" content="light" />
    <meta property="og:type" content="website" />
    <meta property="og:locale" content="vi_VN" />
    <meta property="og:site_name" content="Cái Tiệm Neo" />
    <meta property="og:title" content="Cái Tiệm Neo | Nail có gu ở Thái Hà" />
    <meta property="og:description" content="Xem mẫu móng thật tại tiệm, bảng giá rõ ràng và đặt lịch online trong một phút." />
    <meta property="og:url" content="{{ url('/') }}" />
    <meta property="og:image" content="{{ $shareImage }}" />
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:image" content="{{ $shareImage }}" />
    <title>Cái Tiệm Neo | Nail có gu ở Thái Hà</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo-neo.png') }}" />
    <link rel="apple-touch-icon" href="{{ asset('images/logo-neo.png') }}" />
    @if ($heroPhoto)
      <link rel="preload" as="image" href="{{ $heroPhoto->url() }}" imagesrcset="{{ $heroPhoto->srcset() }}" imagesizes="(max-width: 760px) 88vw, 30vw" />
    @endif
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,400..600;1,400..600&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
  </head>
  <body>
    <a class="skip-link" href="#noi-dung">Đi đến nội dung chính</a>
    <header class="site-header" data-header>
      <div class="header-inner">
        <a class="brand" href="#dau-trang" aria-label="Cái Tiệm Neo — về đầu trang"><span class="brand-mark" aria-hidden="true">N</span><span class="brand-name">Cái Tiệm Neo</span></a>
        <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="menu-chinh" aria-label="Mở menu" data-menu-toggle><span class="sr-only">Mở menu</span><span class="menu-line" aria-hidden="true"></span><span class="menu-line" aria-hidden="true"></span></button>
        <nav id="menu-chinh" class="main-nav" aria-label="Điều hướng chính" data-menu>
          <a href="#mau-mong">Mẫu móng</a><a href="#bang-gia">Bảng giá</a><a href="#gioi-thieu">Về tiệm</a><a href="#lien-he">Ghé tiệm</a><a class="nav-cta" href="#dat-lich">Đặt lịch <span aria-hidden="true">↗</span></a>
        </nav>
      </div>
    </header>
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
            <div class="gallery-controls">
              <button class="gallery-arrow" type="button" data-gallery-prev aria-label="Xem những mẫu trước"><span aria-hidden="true">←</span></button>
              <button class="gallery-arrow" type="button" data-gallery-next aria-label="Xem thêm mẫu"><span aria-hidden="true">→</span></button>
            </div>
          </div>
          <ul class="gallery-rail" data-gallery-rail>
            @foreach ($gallery as $index => $photo)
              <li>
                <button class="gallery-item" type="button" data-gallery-open="{{ $index }}" aria-label="Phóng to mẫu móng số {{ $index + 1 }}">
                  <img src="{{ $photo->url() }}" srcset="{{ $photo->srcset() }}" sizes="(max-width: 760px) 68vw, 300px" width="{{ $photo->width }}" height="{{ $photo->height }}" alt="Mẫu móng số {{ $index + 1 }} do Cái Tiệm Neo thực hiện" loading="lazy" decoding="async" />
                  <span class="gallery-item-index" aria-hidden="true">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                </button>
              </li>
            @endforeach
          </ul>
          <p class="gallery-hint section-shell"><span aria-hidden="true">↔</span> Vuốt ngang để xem hết {{ $gallery->count() }} mẫu, chạm vào ảnh để phóng to.</p>
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
          @if (session('booking_success'))
            <div class="booking-alert booking-success" role="status">{{ session('booking_success') }}</div>
          @endif
          @if ($errors->any())
            <div class="booking-alert" role="alert">
              <p>Vui lòng kiểm tra lại các thông tin sau:</p>
              <ul>
                @foreach ($errors->all() as $error)
                  <li>{{ $error }}</li>
                @endforeach
              </ul>
            </div>
          @endif
          <form action="{{ route('booking.store') }}" method="POST" class="booking-form" data-booking-form>
            <p class="booking-submit-status" data-booking-submit-status role="status" aria-live="polite" hidden></p>
            @csrf
            @if ($branches->count() === 1)
              <input type="hidden" name="branch_id" value="{{ $branches->first()->id }}" />
            @else
              <label>Chi nhánh<select name="branch_id" required><option value="">Chọn chi nhánh</option>@foreach ($branches as $branch)<option value="{{ $branch->id }}" @selected((string) old('branch_id') === (string) $branch->id)>{{ $branch->name }}</option>@endforeach</select></label>
              @error('branch_id')<small>{{ $message }}</small>@enderror
            @endif
            <label>Họ và tên<input name="customer_name" value="{{ old('customer_name') }}" required autocomplete="name" /></label>
            @error('customer_name')<small>{{ $message }}</small>@enderror
            <label>Số điện thoại<input name="customer_phone" value="{{ old('customer_phone') }}" required inputmode="tel" autocomplete="tel" /></label>
            @error('customer_phone')<small>{{ $message }}</small>@enderror
            <label>Thời gian mong muốn<input type="datetime-local" name="starts_at" value="{{ old('starts_at') }}" min="{{ now('Asia/Ho_Chi_Minh')->addHour()->format('Y-m-d\\TH:i') }}" required /></label>
            @error('starts_at')<small>{{ $message }}</small>@enderror
            <label>Thời lượng dự kiến<select name="duration_minutes" required><option value="60" @selected(old('duration_minutes', 60) == 60)>60 phút</option><option value="90" @selected(old('duration_minutes') == 90)>90 phút</option><option value="120" @selected(old('duration_minutes') == 120)>120 phút</option><option value="150" @selected(old('duration_minutes') == 150)>150 phút</option></select></label>
            @error('duration_minutes')<small>{{ $message }}</small>@enderror
            <fieldset class="service-picker">
              <legend>Dịch vụ bạn quan tâm <span>(có thể chọn nhiều)</span></legend>
              <p class="service-picker-summary" data-service-summary aria-live="polite">Chưa chọn dịch vụ nào.</p>
              @forelse ($serviceGroups as $label => $rows)
                @php
                    // Nhóm nào đang có dịch vụ được chọn thì phải mở sẵn, nếu không
                    // sau một lần lỗi xác thực khách sẽ tưởng lựa chọn của mình bay mất.
                    $pickedInGroup = $rows->filter(fn ($service): bool => in_array($service->id, old('service_ids', [])))->count();
                @endphp
                <details class="service-group" @if ($loop->first || $pickedInGroup > 0) open @endif>
                  <summary>
                    <span class="service-group-name">{{ $label }}</span>
                    <span class="service-group-meta @if ($pickedInGroup > 0) is-picked @endif" data-service-group-meta data-service-total="{{ $rows->count() }}">{{ $pickedInGroup > 0 ? $pickedInGroup.' đã chọn' : $rows->count().' dịch vụ' }}</span>
                  </summary>
                  <div class="service-options">
                    @foreach ($rows as $service)
                      <label class="service-option"><input type="checkbox" name="service_ids[]" value="{{ $service->id }}" @checked(in_array($service->id, old('service_ids', []))) /><span>{{ $service->name }}</span><b>{{ $service->formatted_price }}</b></label>
                    @endforeach
                  </div>
                </details>
              @empty
                <p class="empty-note">Tiệm sẽ tư vấn dịch vụ phù hợp khi xác nhận lịch.</p>
              @endforelse
              @error('service_ids')<small>{{ $message }}</small>@enderror
              {{-- Lỗi của từng dịch vụ được chọn: khóa là service_ids.N nên phải
                   duyệt qua, @error không nhận ký tự đại diện. --}}
              @foreach ($errors->get('service_ids.*') as $serviceErrors)<small>{{ $serviceErrors[0] }}</small>@endforeach
            </fieldset>
            <label>Ghi chú<textarea name="note" rows="3" placeholder="Màu sắc, mẫu móng hoặc điều bạn muốn trao đổi…">{{ old('note') }}</textarea></label>
            @error('note')<small>{{ $message }}</small>@enderror
            <button class="button button-primary" type="submit">Gửi yêu cầu đặt lịch <span aria-hidden="true">↗</span></button>
          </form>
        </div>
      </section>

      @if (session('booking_success'))
        <dialog class="booking-success-dialog" data-booking-success-dialog aria-labelledby="booking-success-title">
          <div class="booking-success-dialog__content">
            <span class="booking-success-dialog__mark" aria-hidden="true">✓</span>
            <p class="eyebrow">Đặt lịch thành công</p>
            <h2 id="booking-success-title">Tiệm đã nhận yêu cầu của bạn.</h2>
            <p>{{ session('booking_success') }}</p>
            <button class="button button-primary" type="button" data-booking-success-close autofocus>
              Đã hiểu <span aria-hidden="true">→</span>
            </button>
          </div>
        </dialog>
      @endif
    </main>
    <footer class="site-footer"><div class="section-shell footer-inner"><a class="brand" href="#dau-trang" aria-label="Cái Tiệm Neo — về đầu trang"><span class="brand-mark" aria-hidden="true">N</span><span class="brand-name">Cái Tiệm Neo</span></a><p>nail studio · Hà Nội</p><nav class="social-links" aria-label="Mạng xã hội"><a href="https://www.instagram.com/caitiemneo/" target="_blank" rel="noopener noreferrer">Instagram <span aria-hidden="true">↗</span></a><a href="https://www.tiktok.com/@caitiemneo_" target="_blank" rel="noopener noreferrer">TikTok <span aria-hidden="true">↗</span></a></nav></div></footer>

    {{-- Thanh thao tác cố định: trên điện thoại, gọi và đặt lịch luôn trong tầm ngón cái. --}}
    <nav class="quick-actions" aria-label="Thao tác nhanh">
      <a href="tel:0826881094"><span aria-hidden="true">☏</span> Gọi tiệm</a>
      <a class="quick-actions-primary" href="#dat-lich"><span aria-hidden="true">✦</span> Đặt lịch</a>
    </nav>

    @if ($gallery->isNotEmpty())
      <dialog class="lightbox" data-lightbox aria-label="Ảnh mẫu móng phóng to">
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
