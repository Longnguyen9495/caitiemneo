<?php

namespace App\Services\Ai\Actions;

use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Redirector;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Validator as ValidatorFactory;

/**
 * Hai cách kiểm dữ liệu của một phiếu.
 *
 * Ưu tiên `formRequest`: mỗi thao tác ghi trong khu quản trị đã có sẵn một
 * FormRequest giữ luật, quyền, tên trường tiếng Việt và cả những phép kiểm
 * chéo khó viết lại. Dùng lại nó nghĩa là phiếu của trợ lý không bao giờ lỏng
 * tay hơn form thường, và sửa luật một chỗ thì cả hai đường cùng đổi.
 *
 * `inline` dành cho vài thao tác chỉ tồn tại ở phía trợ lý.
 */
final class Validation
{
    /**
     * @param  array<string, mixed>|Closure(?int $branchId): array<string, mixed>  $rules
     * @param  array<string, string>  $attributes
     * @return Closure(array<string, mixed>, User, ?int): array<string, mixed>
     */
    public static function inline(array|Closure $rules, array $attributes = []): Closure
    {
        return function (array $payload, User $actor, ?int $branchId, ?Model $subject) use ($rules, $attributes): array {
            $resolved = $rules instanceof Closure ? $rules($branchId) : $rules;

            return ValidatorFactory::make($payload, $resolved, [], $attributes)->validate();
        };
    }

    /**
     * Dựng lại FormRequest tương ứng rồi chạy đúng vòng đời của nó: gắn quyền,
     * chuẩn hóa dữ liệu, kiểm luật, kiểm chéo.
     *
     * @param  class-string<FormRequest>  $request
     * @param  string|null  $routeKey  Tên tham số route mà FormRequest đọc bằng
     *                                 $this->route('...'), ví dụ 'service'. Bản
     *                                 ghi đích do ActionDefinition tra sẵn.
     * @return Closure(array<string, mixed>, User, ?int, ?Model): array<string, mixed>
     */
    public static function formRequest(string $request, ?string $routeKey = null): Closure
    {
        return function (array $payload, User $actor, ?int $branchId, ?Model $subject) use ($request, $routeKey): array {
            /** @var FormRequest $form */
            $form = $request::create('/', 'POST', $payload);
            $form->setContainer(app())->setRedirector(app(Redirector::class));
            $form->setUserResolver(fn (): User => $actor);

            $route = new Route('POST', '/', []);
            $route->bind($form);

            if ($routeKey !== null && $subject !== null) {
                $route->setParameter($routeKey, $subject);
            }

            $form->setRouteResolver(fn (): Route => $route);

            // Ném ValidationException khi sai luật và AuthorizationException khi
            // người duyệt không có quyền — đúng hai loại lỗi lớp trên đã xử lý.
            $form->validateResolved();

            return $form->validated();
        };
    }
}
