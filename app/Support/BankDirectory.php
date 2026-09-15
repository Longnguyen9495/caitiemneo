<?php

namespace App\Support;

/**
 * Danh mục ngân hàng đọc từ `config/banks.php`.
 *
 * Một chỗ duy nhất trả lời hai câu hỏi: ô chọn hiển thị những gì, và giá trị
 * nào được phép lưu. Hai câu đó mà tách ra hai nơi thì sớm muộn cũng lệch,
 * và biểu hiện là nhân viên chọn được một ngân hàng rồi bị báo lỗi khi lưu.
 */
final class BankDirectory
{
    /**
     * @return list<array{code: string, name: string, full_name: string}>
     */
    public static function all(): array
    {
        /** @var list<array{code: string, name: string, full_name: string}> $banks */
        $banks = config('banks', []);

        return $banks;
    }

    /**
     * Các giá trị hợp lệ của `users.bank_name`, dùng cho `Rule::in`.
     *
     * @return list<string>
     */
    public static function names(): array
    {
        return array_column(self::all(), 'name');
    }

    /**
     * Dữ liệu cho ô chọn có tìm kiếm.
     *
     * `hint` vừa là dòng phụ hiển thị dưới tên ngân hàng, vừa là phần văn bản
     * để tìm kiếm — nhờ nó mà gõ "vcb" hay "ngoại thương" đều ra Vietcombank
     * chứ không chỉ mỗi tên ngắn.
     *
     * @return list<array{value: string, label: string, hint: string}>
     */
    public static function options(): array
    {
        return array_map(static fn (array $bank): array => [
            'value' => $bank['name'],
            'label' => $bank['name'],
            'hint' => $bank['code'].' · '.$bank['full_name'],
        ], self::all());
    }
}
