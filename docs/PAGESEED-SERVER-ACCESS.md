# Truy cập VPS PageSeed

## Thông tin kết nối

| Thuộc tính | Giá trị |
|---|---|
| Máy chủ | `221.121.1.68` |
| Tài khoản | `root` |
| Cổng SSH | `22` |
| Private key trên máy Windows | `C:\Users\thanh\.ssh\pageseed_bizfly` |
| Hostname đã kiểm chứng | `Pageseed` |

## Đăng nhập từ Windows PowerShell

```powershell
ssh -i "C:\Users\thanh\.ssh\pageseed_bizfly" root@221.121.1.68
```

Kiểm tra kết nối không mở phiên tương tác:

```powershell
ssh -i "C:\Users\thanh\.ssh\pageseed_bizfly" -o BatchMode=yes -o ConnectTimeout=10 root@221.121.1.68 "hostname"
```

## Vị trí project trên VPS

- PageSeed production: `/opt/pageseed/current`
- Cái Tiệm Neo: `/home/rexllm/workspace/caitiemneo`

Thư mục Cái Tiệm Neo được xác minh bằng thao tác chỉ đọc ngày 11/09/2026:

```powershell
ssh -i "C:\Users\thanh\.ssh\pageseed_bizfly" -o BatchMode=yes root@221.121.1.68 "find /opt /var/www /home -maxdepth 5 -type d -iname caitiemneo 2>/dev/null"
```

Kết quả:

```text
/home/rexllm/workspace/caitiemneo
```

## Lưu ý an toàn

- Không đưa nội dung private key, mật khẩu, token hoặc nội dung file `.env` vào repository, log hay hội thoại.
- File này chỉ lưu đường dẫn local đến private key; private key không nằm trong project.
- Chỉ thực hiện thao tác đọc/kiểm tra nếu chưa có phê duyệt triển khai.
- Không tự ý deploy, restart service, chạy migration, sửa Nginx, firewall hoặc cấu hình SSH.
- Với PageSeed, quy trình production phải tuân theo tài liệu và deploy wrapper của chính project PageSeed.
