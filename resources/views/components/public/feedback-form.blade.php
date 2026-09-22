{{--
    Ô gửi feedback của khách.

    Ba trường, không hỏi số điện thoại hay email: khách đang đứng ở trang chủ
    và chỉ muốn nói một câu, mỗi ô thêm vào là một cái cớ để họ thôi không gửi.

    Lỗi đọc từ túi `feedback` chứ không phải túi mặc định, vì trang này còn một
    biểu mẫu đặt lịch — xem `FeedbackController`.
--}}
<form action="{{ route('feedback.store') }}" method="POST" class="feedback-form">
  @csrf
  <fieldset class="rating-picker">
    <legend>Bạn chấm tiệm mấy sao?</legend>
    {{-- Năm ô chọn xếp ngược trong DOM (5 → 1) rồi đảo lại bằng CSS, để quy tắc
         `:checked ~ label` tô được cả những sao bên trái ngôi sao đang chọn.
         Không cần JavaScript, và bàn phím vẫn đi qua được từng ô. --}}
    <div class="rating-stars">
      @foreach ([5, 4, 3, 2, 1] as $value)
        <input type="radio" id="feedback-rating-{{ $value }}" name="rating" value="{{ $value }}" @checked((int) old('rating', 5) === $value) />
        <label for="feedback-rating-{{ $value }}"><span class="sr-only">{{ $value }} sao</span><span aria-hidden="true">★</span></label>
      @endforeach
    </div>
  </fieldset>
  @error('rating', 'feedback')<small>{{ $message }}</small>@enderror
  <label>Tên bạn muốn hiện trên trang<input name="author_name" value="{{ old('author_name') }}" required maxlength="255" autocomplete="name" placeholder="Ví dụ: Linh, chị Hà…" /></label>
  @error('author_name', 'feedback')<small>{{ $message }}</small>@enderror
  <label>Nhận xét của bạn<textarea name="content" rows="4" required minlength="10" maxlength="1000" placeholder="Bạn làm mẫu nào, thợ chăm thế nào, điều gì bạn thích ở tiệm…">{{ old('content') }}</textarea></label>
  @error('content', 'feedback')<small>{{ $message }}</small>@enderror
  <p class="feedback-form__note">Tiệm đọc qua trước khi đăng lên trang, nên feedback của bạn sẽ hiện sau một lúc.</p>
  <button class="button button-primary" type="submit">Gửi feedback <span aria-hidden="true">↗</span></button>
</form>
