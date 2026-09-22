{{-- Lời xác nhận sau khi gửi biểu mẫu, hiện trên mọi trang có ô đặt lịch. --}}
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
