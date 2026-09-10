# Cái Tiệm Neo

Website giới thiệu tĩnh cho **Cái Tiệm Neo**, tiệm nail tại 47 ngõ 131 Thái Hà, Đống Đa, Hà Nội.

## Liên hệ

- Điện thoại: [0826 881 094](tel:0826881094)
- Instagram: [@caitiemneo](https://www.instagram.com/caitiemneo/)
- TikTok: [@caitiemneo_](https://www.tiktok.com/@caitiemneo_)
- Địa chỉ: [47 ngõ 131 Thái Hà, Đống Đa, Hà Nội](https://www.google.com/maps/search/?api=1&query=47%20ng%C3%B5%20131%20Th%C3%A1i%20H%C3%A0%2C%20%C4%90%E1%BB%91ng%20%C4%90a%2C%20H%C3%A0%20N%E1%BB%99i)

Website chỉ sử dụng các thông tin liên hệ đã được xác minh. Không công bố giá, giờ mở cửa, đánh giá hay danh mục dịch vụ chưa được tiệm xác nhận.

## Chạy cục bộ

Đây là static site không cần cài dependency. Từ thư mục repository, chạy:

```bash
python3 -m http.server 8000
```

Sau đó mở <http://localhost:8000>.

## Triển khai GitHub Pages

Website được thiết kế để xuất bản trực tiếp từ nhánh `main`, thư mục gốc (`/`). Sau khi GitHub Pages được bật trong repository, URL dự kiến là:

<https://longnguyen9495.github.io/caitiemneo/>

## Cấu trúc

- `index.html` — nội dung, cấu trúc ngữ nghĩa và metadata.
- `styles.css` — giao diện responsive, phong cách hiện đại pha vintage.
- `script.js` — menu di động có hỗ trợ accessibility và hiệu ứng xuất hiện giảm dần an toàn khi JavaScript không chạy.
