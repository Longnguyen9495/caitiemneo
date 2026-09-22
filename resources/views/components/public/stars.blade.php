{{--
    Năm ngôi sao, tô đầy tới mức khách chấm.

    `role="img"` kèm nhãn chữ: người dùng trình đọc màn hình nghe "5 trên 5
    sao" thay vì năm dấu hoa thị liên tiếp.
--}}
@props(['rating'])
<span {{ $attributes->merge(['class' => 'stars']) }} role="img" aria-label="{{ $rating }} trên 5 sao">
  @for ($star = 1; $star <= 5; $star++)<span @class(['is-on' => $star <= $rating]) aria-hidden="true">★</span>@endfor
</span>
