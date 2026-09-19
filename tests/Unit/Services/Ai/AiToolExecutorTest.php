<?php

namespace Tests\Unit\Services\Ai;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\User;
use App\Services\Ai\AiToolExecutor;
use App\Services\Ai\AiToolRegistry;
use App\Services\ReportService;
use App\Support\BranchContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class AiToolExecutorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Session::forget('admin.current_branch_id');
    }

    private function executorFor(User $user, ?int $branchId = null): AiToolExecutor
    {
        $request = Request::create('/ai-tool');
        $request->setLaravelSession(Session::driver());

        if ($branchId !== null) {
            $request->session()->put(BranchContext::SESSION_KEY, $branchId);
        } elseif ($user->isOwner()) {
            $request->session()->put(BranchContext::SESSION_KEY, BranchContext::ALL);
        }

        app()->bind('request', fn () => $request);
        app()->forgetInstance(BranchContext::class);

        $branchContext = new BranchContext($request);
        $branchContext->resolve($user);
        app()->instance(BranchContext::class, $branchContext);

        return new AiToolExecutor($branchContext, app(ReportService::class), new AiToolRegistry);
    }

    private function appointmentAt(Branch $branch, string $customer, string $phone): Appointment
    {
        return Appointment::factory()->create([
            'branch_id' => $branch->id,
            'customer_name' => $customer,
            'customer_phone' => $phone,
            'starts_at' => now()->addDay()->setTime(14, 0),
            'ends_at' => now()->addDay()->setTime(15, 0),
            'duration_minutes' => 60,
            'status' => AppointmentStatus::Pending,
        ]);
    }

    public function test_find_appointment_returns_the_real_identifier(): void
    {
        // Không có bước này thì model chỉ còn cách đoán mã lịch hẹn khi người
        // dùng bảo "đổi giờ hẹn của chị Lan", và đoán sai là sửa nhầm khách.
        $branch = Branch::factory()->create(['is_active' => true]);
        $owner = User::factory()->owner()->create();
        $appointment = $this->appointmentAt($branch, 'Nguyễn Thị Lan', '0901234567');

        $result = $this->executorFor($owner)->run($owner, 'find_appointment', ['keyword' => 'Lan']);

        $this->assertSame(1, $result['match_count']);
        $this->assertSame($appointment->id, $result['appointments'][0]['id']);
        $this->assertSame('0901234567', $result['appointments'][0]['customer_phone']);
    }

    public function test_find_appointment_matches_on_phone_number(): void
    {
        $branch = Branch::factory()->create(['is_active' => true]);
        $owner = User::factory()->owner()->create();
        $this->appointmentAt($branch, 'Nguyễn Thị Lan', '0901234567');

        $result = $this->executorFor($owner)->run($owner, 'find_appointment', ['keyword' => '0901234']);

        $this->assertSame(1, $result['match_count']);
    }

    public function test_a_tool_cannot_reach_a_branch_the_user_may_not_see(): void
    {
        $allowed = Branch::factory()->create(['is_active' => true]);
        $forbidden = Branch::factory()->create(['is_active' => true]);
        $manager = User::factory()->manager()->atBranch($allowed)->create();

        $this->appointmentAt($allowed, 'Khách được xem', '0900000001');
        $this->appointmentAt($forbidden, 'Khách chi nhánh khác', '0900000002');

        // Model tự đưa branch_id của chi nhánh ngoài phạm vi. Tham số đó phải bị
        // bỏ qua, nếu không công cụ trở thành đường vòng qua phân quyền.
        $result = $this->executorFor($manager, $allowed->id)
            ->run($manager, 'get_appointments', [
                'from' => now()->toDateString(),
                'to' => now()->addDays(7)->toDateString(),
                'branch_id' => $forbidden->id,
            ]);

        $names = array_column($result['appointments'], 'customer_name');
        $this->assertContains('Khách được xem', $names);
        $this->assertNotContains('Khách chi nhánh khác', $names);
    }

    public function test_cash_flow_is_refused_for_a_user_without_the_permission(): void
    {
        $branch = Branch::factory()->create(['is_active' => true]);
        $employee = User::factory()->employee()->atBranch($branch)->create();

        $result = $this->executorFor($employee, $branch->id)
            ->run($employee, 'get_cash_flow', [
                'from' => now()->startOfMonth()->toDateString(),
                'to' => now()->endOfMonth()->toDateString(),
            ]);

        $this->assertArrayHasKey('error', $result);
        $this->assertArrayNotHasKey('transactions', $result);
    }

    public function test_payroll_is_refused_for_a_user_without_the_permission(): void
    {
        $branch = Branch::factory()->create(['is_active' => true]);
        $employee = User::factory()->employee()->atBranch($branch)->create();

        $result = $this->executorFor($employee, $branch->id)
            ->run($employee, 'get_payroll', [
                'from' => now()->startOfMonth()->toDateString(),
                'to' => now()->endOfMonth()->toDateString(),
            ]);

        $this->assertArrayHasKey('error', $result);
        $this->assertArrayNotHasKey('payrolls', $result);
    }

    public function test_an_unknown_tool_is_rejected(): void
    {
        $branch = Branch::factory()->create(['is_active' => true]);
        $owner = User::factory()->owner()->atBranch($branch)->create();

        $result = $this->executorFor($owner)->run($owner, 'drop_all_tables', []);

        $this->assertArrayHasKey('error', $result);
    }

    public function test_the_row_limit_is_capped_however_large_the_model_asks(): void
    {
        $branch = Branch::factory()->create(['is_active' => true]);
        $owner = User::factory()->owner()->create();

        for ($i = 0; $i < 3; $i++) {
            $this->appointmentAt($branch, "Khách {$i}", '090000000'.$i);
        }

        // Một limit khổng lồ không được phép kéo cả bảng vào prompt.
        $result = $this->executorFor($owner)->run($owner, 'get_appointments', [
            'from' => now()->toDateString(),
            'to' => now()->addDays(7)->toDateString(),
            'limit' => 100000,
        ]);

        $this->assertLessThanOrEqual(AiToolRegistry::MAX_LIMIT, count($result['appointments']));
    }

    public function test_like_wildcards_in_a_keyword_do_not_match_everything(): void
    {
        $branch = Branch::factory()->create(['is_active' => true]);
        $owner = User::factory()->owner()->create();
        $this->appointmentAt($branch, 'Nguyễn Thị Lan', '0901234567');

        // Không thoát ký tự đại diện thì "%" quét sạch bảng và model nhận về dữ
        // liệu của khách không liên quan tới câu hỏi.
        $result = $this->executorFor($owner)->run($owner, 'find_appointment', ['keyword' => '%']);

        $this->assertSame(0, $result['match_count']);
    }
}
