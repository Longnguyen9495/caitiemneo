<?php

namespace App\Services\Ai\Actions;

use App\Enums\ServiceCategory;
use App\Enums\ServiceUnit;
use App\Http\Requests\Admin\ProductRequest;
use App\Http\Requests\Admin\ServiceRequest;
use App\Http\Requests\Admin\SupplierRequest;
use App\Http\Requests\Admin\WorkShiftRequest;
use App\Models\Product;
use App\Models\Service;
use App\Models\Supplier;
use App\Models\User;
use App\Models\WorkShift;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Danh mục dùng chung: dịch vụ, vật tư, nhà cung cấp, ca làm.
 *
 * Bốn đối tượng này đều theo một khuôn: FormRequest giữ luật và quyền, còn
 * controller chỉ gọi create hoặc update. Nên ở đây cũng chỉ cần khai một lần
 * cho mỗi cặp tạo/sửa, phần lặp lại do `pair()` lo.
 *
 * Chúng không thuộc chi nhánh nào nên phiếu không có ô chi nhánh; quyền sửa
 * do policy quyết định, và policy hiện chỉ cho chủ tiệm với quản lý.
 */
final class CatalogueActions
{
    /** @return array<int, ActionDefinition> */
    public static function all(): array
    {
        return array_merge(
            self::pair(
                resource: 'service',
                noun: 'dịch vụ',
                model: Service::class,
                request: ServiceRequest::class,
                routeKey: 'service',
                picker: fn (): array => Catalogue::allServices(),
                fields: fn (): array => [
                    Field::text('name', 'Tên dịch vụ')->required(),
                    Field::select('category', 'Nhóm dịch vụ', self::enumOptions(ServiceCategory::class))->required(),
                    Field::select('unit', 'Đơn vị tính', self::enumOptions(ServiceUnit::class))->required(),
                    Field::money('price', 'Giá niêm yết')->required(),
                    Field::money('price_min', 'Giá sàn')->help('Khai thì phải khai cả giá trần.'),
                    Field::money('price_max', 'Giá trần'),
                    Field::number('display_order', 'Thứ tự hiển thị')->attributes(['min' => 0, 'max' => 999]),
                    Field::textarea('description', 'Mô tả'),
                    Field::toggle('is_active', 'Đang bán')->default(true),
                ],
            ),
            self::pair(
                resource: 'product',
                noun: 'vật tư',
                model: Product::class,
                request: ProductRequest::class,
                routeKey: 'product',
                picker: fn (): array => Catalogue::allProducts(),
                fields: fn (): array => [
                    Field::text('name', 'Tên vật tư')->required(),
                    Field::text('sku', 'Mã SKU'),
                    Field::text('unit', 'Đơn vị tính')->required()->help('Chai, hộp, cái…'),
                    Field::money('cost_price', 'Giá vốn')->required(),
                    Field::number('minimum_stock', 'Định mức tồn tối thiểu')->required()->attributes(['step' => 'any', 'min' => 0]),
                    Field::toggle('is_active', 'Còn dùng')->default(true),
                ],
            ),
            self::pair(
                resource: 'supplier',
                noun: 'nhà cung cấp',
                model: Supplier::class,
                request: SupplierRequest::class,
                routeKey: 'supplier',
                picker: fn (): array => Catalogue::suppliers(),
                fields: fn (): array => [
                    Field::text('name', 'Tên nhà cung cấp')->required(),
                    Field::phone('phone', 'Số điện thoại'),
                    Field::email('email', 'Email'),
                    Field::textarea('address', 'Địa chỉ'),
                    Field::toggle('is_active', 'Còn hợp tác')->default(true),
                ],
            ),
            self::pair(
                resource: 'work_shift',
                noun: 'ca làm',
                model: WorkShift::class,
                request: WorkShiftRequest::class,
                routeKey: 'work_shift',
                picker: fn (): array => Catalogue::workShifts(),
                fields: fn (): array => [
                    Field::text('name', 'Tên ca')->required()->help('Ca sáng, ca chiều…'),
                    Field::text('starts_at', 'Giờ bắt đầu')->required()->help('Dạng 08:00.'),
                    Field::text('ends_at', 'Giờ kết thúc')->required()->help('Dạng 17:00.'),
                    Field::number('shift_value', 'Hệ số công')->default(1)->required()->attributes(['step' => 'any', 'min' => 0, 'max' => 10]),
                    Field::number('grace_minutes', 'Phút cho phép đi muộn')->default(0)->required()->attributes(['min' => 0, 'max' => 120]),
                    Field::number('early_check_in_minutes', 'Phút cho phép chấm sớm')->attributes(['min' => 0, 'max' => 240]),
                    Field::select('branch_id', 'Chi nhánh áp dụng', fn (): array => Catalogue::branchesInScope())
                        ->help('Để trống nếu là ca dùng chung mọi chi nhánh.'),
                    Field::toggle('is_active', 'Đang dùng')->default(true),
                ],
            ),
        );
    }

    /**
     * Một cặp tạo/sửa cho cùng một đối tượng danh mục.
     *
     * @param  class-string<Model>  $model
     * @param  class-string<FormRequest>  $request
     * @param  Closure(): array<string, string>  $picker  Danh sách để chọn bản ghi cần sửa.
     * @param  Closure(): array<int, Field>  $fields
     * @return array<int, ActionDefinition>
     */
    private static function pair(
        string $resource,
        string $noun,
        string $model,
        string $request,
        string $routeKey,
        Closure $picker,
        Closure $fields,
    ): array {
        $idKey = $resource.'_id';

        return [
            new ActionDefinition(
                key: 'create_'.$resource,
                label: 'Tạo '.$noun,
                operation: 'create',
                resource: $resource,
                destructive: false,
                fields: fn (?int $branchId): array => $fields(),
                validator: Validation::formRequest($request),
                handler: fn (array $payload, User $actor, ?Model $subject): Model => $model::query()->create($payload),
                branchScoped: false,
            ),
            new ActionDefinition(
                key: 'update_'.$resource,
                label: 'Sửa '.$noun,
                operation: 'update',
                resource: $resource,
                destructive: false,
                fields: fn (?int $branchId): array => array_merge(
                    [Field::select($idKey, ucfirst($noun).' cần sửa', fn (): array => $picker())->required()],
                    $fields(),
                ),
                validator: Validation::formRequest($request, $routeKey),
                handler: function (array $payload, User $actor, ?Model $subject) use ($idKey): Model {
                    unset($payload[$idKey]);
                    $subject->update($payload);

                    return $subject;
                },
                subject: Subject::of($model, $idKey, $noun),
                branchScoped: false,
                hint: 'Giữ nguyên giá trị cũ ở những trường người dùng không yêu cầu đổi.',
            ),
        ];
    }

    /**
     * @param  class-string  $enum  Enum có sẵn hàm options() trả về khóa = nhãn.
     * @return array<string, string>
     */
    private static function enumOptions(string $enum): array
    {
        return call_user_func([$enum, 'options']);
    }
}
