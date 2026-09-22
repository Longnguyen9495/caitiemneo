<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Ai\Actions\ActionRegistry;
use App\Services\Ai\AiPayloadPresenter;
use App\Services\Ai\Contracts\AiProvider;
use App\Services\Ai\OpenAiCompatibleProvider;
use App\Services\Audit\AuditRecorder;
use App\Support\BranchContext;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Scoped, so a queued job or a console command gets its own clean
        // instance instead of inheriting a web request's branch selection.
        $this->app->scoped(BranchContext::class, fn ($app) => new BranchContext($app['request']));

        // Scoped as well, so every audit event raised while handling one
        // request shares a correlation id and reads back as a single action.
        $this->app->scoped(AuditRecorder::class, fn ($app) => new AuditRecorder($app['request']));

        $this->app->bind(AiProvider::class, OpenAiCompatibleProvider::class);

        // Scoped để mọi đề xuất hiển thị trên cùng một trang dùng chung bộ nhớ
        // tra tên chi nhánh, vật tư, nhân viên — tra một lần thay vì mỗi thẻ.
        $this->app->scoped(AiPayloadPresenter::class);

        // Bản khai thao tác AI dựng một lần cho mỗi request: một trang chat có
        // thể vẽ hàng chục phiếu, không cần ghép lại danh sách từng lần.
        $this->app->scoped(ActionRegistry::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerGates();
        $this->registerPasswordResetMail();

        Blade::directive('money', fn (string $expression): string => "<?php echo e(\App\Support\Money::format({$expression})); ?>");
    }

    /**
     * Email đặt lại mật khẩu mang nội dung tiếng Việt và nhận diện của tiệm.
     */
    private function registerPasswordResetMail(): void
    {
        ResetPassword::toMailUsing(function (User $user, string $token): MailMessage {
            $url = url(route('password.reset', [
                'token' => $token,
                'email' => $user->getEmailForPasswordReset(),
            ], false));
            $expiration = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');
            $name = trim((string) $user->name);

            return (new MailMessage)
                ->subject('Đặt lại mật khẩu Cái Tiệm Neo')
                ->greeting($name !== '' ? "Xin chào {$name}," : 'Xin chào,')
                ->line('Chúng tôi nhận được yêu cầu đặt lại mật khẩu cho tài khoản của bạn tại Cái Tiệm Neo.')
                ->line('Nhấn nút bên dưới để tạo mật khẩu mới:')
                ->action('Đặt lại mật khẩu', $url)
                ->line("Liên kết này sẽ hết hạn sau {$expiration} phút.")
                ->line('Nếu bạn không thực hiện yêu cầu này, bạn có thể bỏ qua email và tài khoản vẫn được bảo mật.')
                ->salutation("Trân trọng,\nCái Tiệm Neo");
        });
    }

    /**
     * Ability gates that are not tied to a single model.
     *
     * Operational reporting is open to the leadership, while anything exposing
     * margins or salary cost stays with the owner.
     */
    private function registerGates(): void
    {
        Gate::define('use-ai-assistant', fn (User $user): bool => $user->isLeadership());
        Gate::define('view-reports', fn (User $user): bool => $user->isOwner() || $user->isManager());
        Gate::define('view-profit-reports', fn (User $user): bool => $user->isOwner());
        Gate::define('view-company-wide', fn (User $user): bool => $user->isOwner());
        Gate::define('view-cash-book', fn (User $user): bool => $user->isOwner() || $user->isManager());
        Gate::define('manage-inventory', fn (User $user): bool => $user->isOwner() || $user->isManager());
        Gate::define('view-inventory', fn (User $user): bool => $user->isOwner() || $user->isManager() || $user->isEmployee());
        Gate::define('manage-staff', fn (User $user): bool => $user->isOwner() || $user->isManager());
        Gate::define('manage-branches', fn (User $user): bool => $user->isOwner());
        Gate::define(
            'manage-payroll',
            fn (User $user): bool => $user->isOwner() || ($user->isManager() && $user->can_manage_payroll),
        );
    }
}
