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

## Cái Tiệm Neo production

| Thành phần | Giá trị hiện tại |
|---|---|
| Website | `https://caitiemneo.221-121-1-68.sslip.io/` |
| Admin | `https://caitiemneo.221-121-1-68.sslip.io/admin` |
| Git worktree (chỉ dùng để pull source) | `/home/rexllm/workspace/caitiemneo` |
| Laravel release đang được Nginx phục vụ | `/var/www/caitiemneo-app` |
| Web root | `/var/www/caitiemneo-app/public` |
| SQLite production | `/var/www/caitiemneo-app/database/database.sqlite` |
| Nginx vhost | `/etc/nginx/sites-available/caitiemneo` |
| PHP-FPM socket | `/run/php/php8.5-fpm.sock` |
| PHP-FPM service | `php8.5-fpm` |
| Commit Laravel triển khai đầu tiên | `8178476` |

### Kiến trúc và ràng buộc

- Nginx **không được** trỏ trực tiếp vào `/home/rexllm/workspace/caitiemneo/public`: thư mục home không mở quyền traverse cho `www-data`.
- Giữ source Git tại `/home/rexllm/workspace/caitiemneo`; deploy bản release sang `/var/www/caitiemneo-app`.
- Vhost Cái Tiệm Neo và vhost PageSeed độc lập. Không sửa `/etc/nginx/sites-available/pageseed` khi chỉ deploy Cái Tiệm Neo.
- File `.env`, database SQLite, `vendor`, `node_modules` và secrets là runtime-only; tuyệt đối không commit hoặc copy ngược về Git worktree.
- Không chạy `DatabaseSeeder`: nó gọi `StaffSeeder` có tài khoản mẫu/mật khẩu mẫu. Chỉ chạy seeder cấu hình được phê duyệt rõ ràng.

### Kiểm tra read-only trước khi thao tác

```powershell
ssh -i "C:\Users\thanh\.ssh\pageseed_bizfly" -o BatchMode=yes root@221.121.1.68 "systemctl is-active nginx php8.5-fpm pageseed; curl -sS -o /dev/null -w 'caitiemneo=%{http_code}\n' https://caitiemneo.221-121-1-68.sslip.io/; curl -sS -o /dev/null -w 'pageseed=%{http_code}\n' https://221-121-1-68.sslip.io/"
```

### Quy trình deploy Laravel

Chỉ thực hiện khi người dùng đã phê duyệt deploy và sau khi code đã được commit/push.

1. Chạy quality gate local: Pint, test phù hợp và `npm run build` khi thay đổi frontend.
2. Pull Git bằng user `rexllm`, không dùng Git root:

```bash
sudo -u rexllm git -C /home/rexllm/workspace/caitiemneo fetch origin main
sudo -u rexllm git -C /home/rexllm/workspace/caitiemneo checkout main
sudo -u rexllm git -C /home/rexllm/workspace/caitiemneo pull --ff-only origin main
```

3. Đồng bộ source vào `/var/www/caitiemneo-app`, đồng thời loại trừ `.git`, `.env`, database SQLite, `vendor` và `node_modules`.
4. Chạy trong release: `composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader`, migration với `--force`, `npm ci`, `npm run build`, rồi xóa `node_modules` nếu không cần giữ.
5. Dùng `.env` production với `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://caitiemneo.221-121-1-68.sslip.io`, `DB_CONNECTION=sqlite`, database path tuyệt đối và session/cache database.
6. Cấp quyền `rexllm:www-data` cho `storage`, `bootstrap/cache` và thư mục `database`. SQLite phải ghi được cả file **và thư mục cha**:

```bash
chown rexllm:www-data /var/www/caitiemneo-app/database /var/www/caitiemneo-app/database/database.sqlite
chmod 2770 /var/www/caitiemneo-app/database
chmod 660 /var/www/caitiemneo-app/database/database.sqlite
```

7. Xóa và tạo lại Laravel caches: `php artisan optimize:clear`, `config:cache`, `route:cache`, `view:cache`.
8. Luôn chạy `nginx -t` trước `systemctl reload nginx`. Giữ nguyên các directive SSL do Certbot quản lý.
9. Xác minh `/`, `/login`, `/admin` (guest phải chuyển về login), asset Vite, migration status, `nginx`, `php8.5-fpm`, và PageSeed.

### Lưu ý an toàn

- Không đưa nội dung private key, mật khẩu, token hoặc nội dung file `.env` vào repository, log hay hội thoại.
- File này chỉ lưu đường dẫn local đến private key; private key không nằm trong project.
- Chỉ thực hiện thao tác đọc/kiểm tra nếu chưa có phê duyệt triển khai.
- Không tự ý deploy, restart service, chạy migration, sửa Nginx, firewall hoặc cấu hình SSH.
- Với PageSeed, quy trình production phải tuân theo tài liệu và deploy wrapper của chính project PageSeed.
