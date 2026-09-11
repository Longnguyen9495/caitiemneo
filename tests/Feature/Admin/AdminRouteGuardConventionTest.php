<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\PayrollController;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

/**
 * A safety net for the next endpoint somebody adds.
 *
 * The whole `/admin` prefix admits the `employee` role, so the role middleware
 * is only a coarse gate: the real permission check lives in each controller
 * action. An action that forgets it is not a subtle bug, it is an open door —
 * and nothing in the framework would complain. This test makes that omission
 * fail the build instead.
 *
 * A guard counts when the action calls `authorize`/`Gate::`/`can(...)` itself,
 * or delegates to a Form Request whose `authorize()` does the same.
 */
class AdminRouteGuardConventionTest extends TestCase
{
    /**
     * Endpoints that deliberately carry no per-action authorization.
     *
     * Each entry needs a reason, because an unexplained exemption is how this
     * safety net quietly stops being one.
     *
     * @var array<string, string>
     */
    private const EXEMPT = [
        // Validates the branch against the account's own postings inline, which
        // is the authorization: there is no model to gate on.
        'admin.branch.switch' => 'Chỉ đổi chi nhánh đang chọn, đã kiểm tra theo phân công của chính tài khoản.',
        // Read-only landing page; each figure is gated individually inside the
        // controller (see SEC-P0-01).
        'admin.dashboard' => 'Từng chỉ số được gate riêng trong controller.',
    ];

    public function test_every_admin_endpoint_performs_an_authorization_check(): void
    {
        $unguarded = [];

        foreach ($this->adminRoutes() as $route) {
            $name = $route->getName() ?? $route->uri();

            if (array_key_exists($name, self::EXEMPT)) {
                continue;
            }

            if (! $this->routeIsGuarded($route)) {
                $unguarded[] = sprintf('%s (%s)', $name, $route->getActionName());
            }
        }

        $this->assertSame([], $unguarded, implode("\n", [
            'Các endpoint admin sau không có kiểm tra phân quyền ở tầng action.',
            'Hãy gọi $this->authorize(...), Gate::authorize(...) hoặc dùng Form Request có authorize().',
            'Nếu thực sự không cần, thêm vào AdminRouteGuardConventionTest::EXEMPT kèm lý do.',
            ...$unguarded,
        ]));
    }

    /** The exemption list must not rot into a dumping ground for stale names. */
    public function test_every_exemption_still_points_at_a_real_route(): void
    {
        $names = array_map(
            fn (RoutingRoute $route): ?string => $route->getName(),
            $this->adminRoutes(),
        );

        foreach (array_keys(self::EXEMPT) as $exempt) {
            $this->assertContains($exempt, $names, "Miễn trừ [$exempt] không còn tương ứng route nào.");
        }
    }

    /** @return array<int, RoutingRoute> */
    private function adminRoutes(): array
    {
        return array_values(array_filter(
            Route::getRoutes()->getRoutes(),
            fn (RoutingRoute $route): bool => Str::startsWith($route->getName() ?? '', 'admin.'),
        ));
    }

    private function routeIsGuarded(RoutingRoute $route): bool
    {
        $action = $route->getActionName();

        if (! Str::contains($action, '\\')) {
            return false;
        }

        [$class, $method] = Str::contains($action, '@')
            ? explode('@', $action, 2)
            : [$action, '__invoke'];

        if (! class_exists($class) || ! method_exists($class, $method)) {
            return false;
        }

        $source = $this->methodSource(new ReflectionMethod($class, $method));

        if ($this->looksGuarded($source)) {
            return true;
        }

        // A Form Request type-hinted on the action carries the check instead.
        foreach ((new ReflectionMethod($class, $method))->getParameters() as $parameter) {
            $type = $parameter->getType();
            $typeName = $type instanceof \ReflectionNamedType ? $type->getName() : null;

            if ($typeName === null || ! class_exists($typeName)) {
                continue;
            }

            if (! is_subclass_of($typeName, FormRequest::class)) {
                continue;
            }

            if (! method_exists($typeName, 'authorize')) {
                continue;
            }

            $authorize = new ReflectionMethod($typeName, 'authorize');

            if ($authorize->getDeclaringClass()->getName() !== $typeName) {
                continue;
            }

            if ($this->looksGuarded($this->methodSource($authorize))) {
                return true;
            }
        }

        return false;
    }

    private function looksGuarded(string $source): bool
    {
        foreach (['authorize(', 'Gate::', '->can(', '->cannot('] as $needle) {
            if (str_contains($source, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function methodSource(ReflectionMethod $method): string
    {
        $file = $method->getFileName();
        $start = $method->getStartLine();
        $end = $method->getEndLine();

        if ($file === false || $start === false || $end === false) {
            return '';
        }

        $lines = array_slice(file($file), $start - 1, $end - $start + 1);

        return implode('', $lines);
    }

    /** Keeps the reflection helper honest about classes it cannot resolve. */
    public function test_the_guard_detector_recognises_a_known_guarded_action(): void
    {
        $reflection = new ReflectionClass(PayrollController::class);

        $this->assertTrue(
            $this->looksGuarded($this->methodSource($reflection->getMethod('index'))),
            'Bộ dò phân quyền không nhận ra một action đã có authorize, nên kết quả test không đáng tin.',
        );
    }
}
