{{--
    Thanh điều hướng của các trang công khai.

    Các mục trong menu trỏ tới từng khu của trang giới thiệu, nên khi đứng ở
    trang khác chúng phải mang theo địa chỉ đầy đủ — nếu không, bấm "Bảng giá"
    ở trang album chỉ nhảy tới một mỏ neo không tồn tại.

    `bookingDialog` bật ở những trang có sẵn hộp đặt lịch: bấm là mở hộp ngay
    tại chỗ thay vì rời trang.
--}}
@props(['bookingDialog' => false])
@php($homeUrl = request()->routeIs('home') ? '' : route('home'))
<header class="site-header" data-header>
  <div class="header-inner">
    <a class="brand" href="{{ $homeUrl ?: '#dau-trang' }}" aria-label="Cái Tiệm Neo — về đầu trang"><span class="brand-mark" aria-hidden="true">N</span><span class="brand-name">Cái Tiệm Neo</span></a>
    <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="menu-chinh" aria-label="Mở menu" data-menu-toggle><span class="sr-only">Mở menu</span><span class="menu-line" aria-hidden="true"></span><span class="menu-line" aria-hidden="true"></span></button>
    <nav id="menu-chinh" class="main-nav" aria-label="Điều hướng chính" data-menu>
      <a href="{{ route('lookbook') }}" @if (request()->routeIs('lookbook')) aria-current="page" @endif>Mẫu móng</a><a href="{{ $homeUrl }}#bang-gia">Bảng giá</a><a href="{{ $homeUrl }}#gioi-thieu">Về tiệm</a><a href="{{ $homeUrl }}#feedback">Feedback</a><a href="{{ $homeUrl }}#lien-he">Ghé tiệm</a>
      @if ($bookingDialog)
        <button class="nav-cta" type="button" data-booking-open>Đặt lịch <span aria-hidden="true">↗</span></button>
      @else
        <a class="nav-cta" href="{{ $homeUrl }}#dat-lich">Đặt lịch <span aria-hidden="true">↗</span></a>
      @endif
    </nav>
  </div>
</header>
