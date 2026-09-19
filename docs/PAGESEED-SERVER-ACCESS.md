# Truy cập VPS PageSeed

## Thông tin kết nối

| Thuộc tính | Giá trị |
|---|---|
| Máy chủ | `221.121.1.68` |
| Tài khoản | `root` |
| Cổng SSH | `22` |
| Private key trên máy Windows | `%USERPROFILE%\.ssh\pageseed_bizfly` |
| Hostname đã kiểm chứng | `Pageseed` |

## Đăng nhập từ Windows PowerShell

```powershell
ssh -i "$env:USERPROFILE\.ssh\pageseed_bizfly" root@221.121.1.68
```

Kiểm tra kết nối không mở phiên tương tác:

```powershell
ssh -i "$env:USERPROFILE\.ssh\pageseed_bizfly" -o BatchMode=yes -o ConnectTimeout=10 root@221.121.1.68 "hostname"
```

## Vị trí project trên VPS

- PageSeed production: `/opt/pageseed/current`
- Cái Tiệm Neo: `/var/www/caitiemneo-app` — **một thư mục duy nhất**, vừa là git checkout vừa là bản Nginx phục vụ.

Từ 18/09/2026 không còn thư mục nguồn riêng ở `/home/rexllm/workspace/caitiemneo`: giữ hai bản
khiến mỗi lần deploy phải rsync, mà rsync ghi đè quyền thư mục `database` làm SQLite mất quyền ghi.
Deploy giờ là `git pull` thẳng trong thư mục đang chạy.

## Cái Tiệm Neo production

| Thành phần | Giá trị hiện tại |
|---|---|
| Website | `https://caitiemneo.221-121-1-68.sslip.io/` |
| Admin | `https://caitiemneo.221-121-1-68.sslip.io/admin` |
| Thư mục project (git checkout + bản chạy) | `/var/www/caitiemneo-app` |
| Web root | `/var/www/caitiemneo-app/public` |
| SQLite production | `/var/www/caitiemneo-app/database/database.sqlite` |
| Nginx vhost | `/etc/nginx/sites-available/caitiemneo` |
| PHP-FPM socket | `/run/php/php8.5-fpm.sock` |
| PHP-FPM service | `php8.5-fpm` |
| Commit Laravel triển khai đầu tiên | `8178476` |

### Kiến trúc và ràng buộc

- Toàn bộ project nằm trong `/var/www/caitiemneo-app`, thuộc user `rexllm`. Mọi lệnh `git`, `composer`, `npm`, `artisan` chạy bằng `sudo -u rexllm`, không chạy bằng root — build bằng root sẽ để lại file root-owned khiến lần deploy sau hỏng (`EACCES` khi Vite dọn `public/build`).
- Không đặt project trong `/home/rexllm`: thư mục home là `750`, `www-data` không traverse được. Mở `o+x` cho home chỉ để phục vụ web là đánh đổi bảo mật không cần thiết khi `/var/www` sẵn sàng.
- Vhost Cái Tiệm Neo và vhost PageSeed độc lập. Không sửa `/etc/nginx/sites-available/pageseed` khi chỉ deploy Cái Tiệm Neo.
- File `.env`, database SQLite, `vendor`, `node_modules` và secrets là runtime-only, đều nằm trong `.gitignore`; tuyệt đối không commit.
- `.env` phải là `640 rexllm:www-data`: `www-data` cần đọc, user khác thì không.
- Không chạy `DatabaseSeeder`: nó gọi `StaffSeeder` có tài khoản mẫu/mật khẩu mẫu. Chỉ chạy seeder cấu hình được phê duyệt rõ ràng.

### Kiểm tra read-only trước khi thao tác

```powershell
ssh -i "$env:USERPROFILE\.ssh\pageseed_bizfly" -o BatchMode=yes root@221.121.1.68 "systemctl is-active nginx php8.5-fpm pageseed; curl -sS -o /dev/null -w 'caitiemneo=%{http_code}\n' https://caitiemneo.221-121-1-68.sslip.io/; curl -sS -o /dev/null -w 'pageseed=%{http_code}\n' https://221-121-1-68.sslip.io/"
```

### Giới hạn upload ảnh và video

Luồng `admin.invoices.pay` nhận một ảnh chứng từ tối đa **5 MB**. Luồng `admin.gallery.store` nhận tối đa **5 tệp**, mỗi tệp tối đa **9 MB**. Cấu hình production phải cho phép toàn bộ request multipart lớn hơn tổng giới hạn Laravel để người dùng nhận được lỗi theo trường thay vì `413 Request Entity Too Large` từ proxy.

Trong vhost `/etc/nginx/sites-available/caitiemneo`, đặt trong khối `server` của Cái Tiệm Neo:

```nginx
client_max_body_size 48m;
```

Trong cấu hình PHP 8.5 FPM đang phục vụ application, đặt:

```ini
upload_max_filesize = 10M
post_max_size = 48M
```

Không đưa các directive này vào vhost PageSeed. Sau khi được phê duyệt áp dụng hạ tầng, kiểm tra `nginx -t`, reload Nginx và PHP-FPM theo quy trình vận hành; không tự ý thay đổi hạ tầng trong lúc deploy code.

### Quy trình deploy Laravel

Chỉ thực hiện khi người dùng đã phê duyệt deploy và sau khi code đã được commit/push.

1. Chạy quality gate local: Pint, test phù hợp và `npm run build` khi thay đổi frontend.
2. Sao lưu SQLite trước khi có migration:

```bash
cp -p /var/www/caitiemneo-app/database/database.sqlite /var/backups/caitiemneo/database-$(date +%Y%m%d-%H%M%S).sqlite
```

3. Pull thẳng trong thư mục đang chạy, bằng user `rexllm`:

```bash
sudo -u rexllm git -C /var/www/caitiemneo-app pull --ff-only origin main
```

4. Chạy trong thư mục đó, đều bằng `sudo -u rexllm`: `composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader`, `php artisan migrate --force`, và khi frontend đổi thì `npm ci && npm run build` rồi `rm -rf node_modules`.
5. `.env` production giữ `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://caitiemneo.221-121-1-68.sslip.io`, `DB_CONNECTION=sqlite`, database path tuyệt đối, session/cache `database`. Trợ lý AI cần thêm `AI_ENABLED=true` và `AI_API_KEY`; thiếu thì trang `/admin/ai` chỉ hiện cảnh báo chưa bật. `.env` **không** đi theo `git pull`, mỗi biến mới phải thêm tay.
6. Cấp quyền `rexllm:www-data` cho `storage`, `bootstrap/cache` và thư mục `database`. SQLite phải ghi được cả file **và thư mục cha**:

```bash
chown rexllm:www-data /var/www/caitiemneo-app/database /var/www/caitiemneo-app/database/database.sqlite
chmod 2770 /var/www/caitiemneo-app/database
chmod 660 /var/www/caitiemneo-app/database/database.sqlite
```

7. Xóa và tạo lại Laravel caches: `php artisan optimize:clear`, `config:cache`, `route:cache`, `view:cache`. Sửa `.env` mà quên `config:cache` thì cấu hình cũ vẫn có hiệu lực.
8. Không cần đụng Nginx khi chỉ deploy code — `root` đã cố định. Nếu buộc phải sửa vhost thì `nginx -t` trước `systemctl reload nginx`, giữ nguyên các directive SSL do Certbot quản lý.
9. Xác minh `/`, `/login`, `/admin` và `/admin/ai` (guest phải chuyển về login), asset Vite, migration status, `nginx`, `php8.5-fpm`, và PageSeed.

### Lưu ý an toàn

- Không đưa nội dung private key, mật khẩu, token hoặc nội dung file `.env` vào repository, log hay hội thoại.
- File này chỉ lưu đường dẫn local đến private key; private key không nằm trong project.
- Chỉ thực hiện thao tác đọc/kiểm tra nếu chưa có phê duyệt triển khai.
- Không tự ý deploy, restart service, chạy migration, sửa Nginx, firewall hoặc cấu hình SSH.
- Với PageSeed, quy trình production phải tuân theo tài liệu và deploy wrapper của chính project PageSeed.
