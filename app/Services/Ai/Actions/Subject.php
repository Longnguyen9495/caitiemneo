<?php

namespace App\Services\Ai\Actions;

use App\Support\BranchContext;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Bản ghi mà một thao tác sửa hoặc hủy nhắm tới.
 *
 * Tra đúng một lần rồi dùng lại cho cả lúc kiểm luật lẫn lúc chạy thật, nên
 * không có khe hở giữa "bản ghi đã kiểm" và "bản ghi bị sửa". Mọi truy vấn đều
 * kèm phạm vi chi nhánh: trợ lý đoán bừa một ID của tiệm khác thì dừng ngay ở
 * đây với câu báo lỗi người đọc hiểu được, chứ không đợi policy ném 403.
 */
final class Subject
{
    /**
     * @param  class-string<Model>  $model
     * @param  string  $key  Khóa trong payload chứa ID, ví dụ 'service_id'.
     * @param  Closure(Builder): Builder|null  $scope  Giới hạn thêm, thường là chi nhánh.
     * @return Closure(array<string, mixed>): Model
     */
    public static function of(string $model, string $key, string $noun, ?Closure $scope = null): Closure
    {
        return function (array $payload) use ($model, $key, $noun, $scope): Model {
            $id = $payload[$key] ?? null;
            $query = $model::query()->whereKey(is_numeric($id) ? (int) $id : 0);

            if ($scope !== null) {
                $query = $scope($query);
            }

            $record = $query->first();

            if ($record === null) {
                throw ValidationException::withMessages([
                    $key => "Không tìm thấy {$noun} này trong phạm vi bạn được phép thao tác.",
                ]);
            }

            return $record;
        };
    }

    /**
     * @param  class-string<Model>  $model
     * @return Closure(array<string, mixed>): Model
     */
    public static function inBranch(string $model, string $key, string $noun, string $column = 'branch_id'): Closure
    {
        return self::of($model, $key, $noun, fn (Builder $query): Builder => $query->whereIn(
            $column,
            app(BranchContext::class)->scopeIds() ?: [0],
        ));
    }
}
