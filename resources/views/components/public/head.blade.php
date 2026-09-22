{{--
    Phần <head> dùng chung cho mọi trang công khai.

    Hai trang cùng một bộ font, một bundle và một ảnh chia sẻ; tách ra đây để
    lần sau đổi font hay đổi ảnh chia sẻ chỉ phải sửa một chỗ.
--}}
@props([
    'title',
    'description',
    'shareTitle' => null,
    'shareDescription' => null,
    'shareImageAlt' => 'Mẫu nail tại Cái Tiệm Neo',
    'preloadPhoto' => null,
    'preloadSizes' => '(max-width: 760px) 88vw, 30vw',
])
@php
    $shareImage = asset('images/social-share.jpg').'?v=20260916';
    $shareTitle ??= $title;
    $shareDescription ??= $description;
@endphp
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="description" content="{{ $description }}" />
<meta name="theme-color" content="#51212b" />
<meta name="color-scheme" content="light" />
<meta property="og:type" content="website" />
<meta property="og:locale" content="vi_VN" />
<meta property="og:site_name" content="Cái Tiệm Neo" />
<meta property="og:title" content="{{ $shareTitle }}" />
<meta property="og:description" content="{{ $shareDescription }}" />
<meta property="og:url" content="{{ url()->current() }}" />
<meta property="og:image" content="{{ $shareImage }}" />
<meta property="og:image:secure_url" content="{{ $shareImage }}" />
<meta property="og:image:type" content="image/jpeg" />
<meta property="og:image:width" content="1200" />
<meta property="og:image:height" content="630" />
<meta property="og:image:alt" content="{{ $shareImageAlt }}" />
<meta name="twitter:card" content="summary_large_image" />
<meta name="twitter:title" content="{{ $shareTitle }}" />
<meta name="twitter:description" content="{{ $shareDescription }}" />
<meta name="twitter:image" content="{{ $shareImage }}" />
<title>{{ $title }}</title>
<link rel="icon" type="image/png" href="{{ asset('images/logo-neo.png') }}" />
<link rel="apple-touch-icon" href="{{ asset('images/logo-neo.png') }}" />
@if ($preloadPhoto)
  <link rel="preload" as="image" href="{{ $preloadPhoto->url() }}" imagesrcset="{{ $preloadPhoto->srcset() }}" imagesizes="{{ $preloadSizes }}" />
@endif
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,400..600;1,400..600&display=swap" rel="stylesheet" />
@vite(['resources/css/app.css', 'resources/js/app.js'])
