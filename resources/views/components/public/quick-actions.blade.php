{{-- Thanh thao tác cố định: trên điện thoại, gọi và đặt lịch luôn trong tầm ngón cái. --}}
@props(['bookingDialog' => false])
<nav class="quick-actions" aria-label="Thao tác nhanh">
  <a href="tel:0826881094"><span aria-hidden="true">☏</span> Gọi tiệm</a>
  @if ($bookingDialog)
    <button class="quick-actions-primary" type="button" data-booking-open><span aria-hidden="true">✦</span> Đặt lịch</button>
  @else
    <a class="quick-actions-primary" href="{{ request()->routeIs('home') ? '' : route('home') }}#dat-lich"><span aria-hidden="true">✦</span> Đặt lịch</a>
  @endif
</nav>
