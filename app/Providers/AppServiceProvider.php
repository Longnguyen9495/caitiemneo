<?php

namespace App\Providers;

use App\Models\User;
use App\Support\BranchContext;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerGates();

        Blade::directive('money', fn (string $expression): string => "<?php echo e(\App\Support\Money::format({$expression})); ?>");
    }

    /**
     * Ability gates that are not tied to a single model.
     *
     * Operational reporting is open to the leadership, while anything exposing
     * margins or salary cost stays with the owner.
     */
    private function registerGates(): void
    {
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
