{{--
    Biểu mẫu đặt lịch của khách.

    Dùng chung cho khu đặt lịch ở trang giới thiệu và hộp đặt lịch bật lên ở
    trang album, để hai nơi luôn hỏi cùng một bộ thông tin và cùng một bảng giá.

    `source` là tên trang gửi biểu mẫu — máy chủ dựa vào đó để trả khách về
    đúng chỗ họ đang đứng, thay vì nhận một địa chỉ tự do từ biểu mẫu.
--}}
@props([
    'branches',
    'serviceGroups',
    'source' => 'home',
    'selectedPhoto' => null,
])
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
  <input type="hidden" name="source" value="{{ $source }}" />
  @if ($source === 'lookbook')
    <input type="hidden" name="gallery_item_id" value="{{ old('gallery_item_id', $selectedPhoto?->id) }}" data-booking-photo-input />
  @endif
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
  <label>Email<input type="email" name="customer_email" value="{{ old('customer_email') }}" autocomplete="email" placeholder="Để nhận thông tin lịch hẹn" /></label>
  @error('customer_email')<small>{{ $message }}</small>@enderror
  <label>Thời gian mong muốn<input type="datetime-local" name="starts_at" value="{{ old('starts_at') }}" min="{{ now('Asia/Ho_Chi_Minh')->addHour()->format('Y-m-d\TH:i') }}" required /></label>
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
