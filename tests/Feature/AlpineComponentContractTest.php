<?php

namespace Tests\Feature;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

/**
 * Mọi `x-data="tên"` trong view phải trỏ tới một `Alpine.data('tên')` có thật.
 *
 * Đã từng có `x-data="clockPanel"` nằm lại sau một lần refactor, trong khi
 * `resources/js/admin.js` chỉ đăng ký `clockButton`. Không có component nào tên
 * `clockPanel`, nên Alpine báo "clockPanel is not defined" ở mỗi lần tải trang
 * chấm công. Lỗi chỉ lộ ra trong `storage/logs/browser.log` — không test nào và
 * không lần chạy CI nào bắt được. Bài test này khép lại lỗ hổng đó.
 *
 * Chỉ soi những giá trị là tên định danh (`clockButton`) hoặc tên kèm tham số
 * (`invoiceEditor(...)`). Biểu thức tại chỗ dạng `x-data="{ open: false }"`
 * không cần đăng ký nên được bỏ qua.
 */
class AlpineComponentContractTest extends TestCase
{
    /** Một tên sai chính tả không được lọt qua CI. */
    public function test_moi_x_data_deu_tro_toi_mot_alpine_component_co_that(): void
    {
        $registered = $this->registeredAlpineComponents();

        // Nếu không đọc được file JS nào thì bài test này vô nghĩa.
        $this->assertContains('submitGuard', $registered);

        $missing = [];

        foreach ($this->bladeFiles() as $file) {
            $contents = file_get_contents($file->getPathname());

            if ($contents === false) {
                continue;
            }

            preg_match_all('/x-data\s*=\s*"([^"]*)"/', $contents, $matches);

            foreach ($matches[1] as $expression) {
                $name = $this->componentName(trim($expression));

                if ($name !== null && ! in_array($name, $registered, true)) {
                    $missing[] = sprintf(
                        '%s → x-data="%s"',
                        str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname()),
                        $expression,
                    );
                }
            }
        }

        $this->assertSame(
            [],
            $missing,
            "x-data đang trỏ tới Alpine component không tồn tại:\n".implode("\n", $missing),
        );
    }

    /** @return list<SplFileInfo> */
    private function bladeFiles(): array
    {
        $files = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(resource_path('views'), RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo
                && $file->isFile()
                && str_ends_with($file->getFilename(), '.blade.php')) {
                $files[] = $file;
            }
        }

        return $files;
    }

    /** @return list<string> */
    private function registeredAlpineComponents(): array
    {
        $names = [];

        foreach (glob(resource_path('js/*.js')) ?: [] as $jsFile) {
            $contents = file_get_contents($jsFile);

            if ($contents === false) {
                continue;
            }

            preg_match_all('/Alpine\.data\(\s*[\'"]([^\'"]+)[\'"]/', $contents, $matches);

            $names = array_merge($names, $matches[1]);
        }

        return array_values(array_unique($names));
    }

    /** Trả về tên component nếu biểu thức là tên định danh, ngược lại null. */
    private function componentName(string $expression): ?string
    {
        // `{ open: false }` và các biểu thức tại chỗ khác không cần đăng ký.
        if ($expression === '' || str_starts_with($expression, '{')) {
            return null;
        }

        if (preg_match('/^([A-Za-z_$][A-Za-z0-9_$]*)/', $expression, $match) !== 1) {
            return null;
        }

        return $match[1];
    }
}
