<?php

namespace Tests\Unit\Services\Ai;

use App\Enums\AppointmentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\User;
use App\Services\Ai\AiBusinessContext;
use App\Services\ReportService;
use App\Support\BranchContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class AiBusinessContextTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Session::forget('admin.current_branch_id');
    }

    private function buildContextFor(User $user, ?int $branchId = null): AiBusinessContext
    {
        $request = Request::create('/ai-context');
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

        return new AiBusinessContext($branchContext, app(ReportService::class));
    }

    public function test_owner_sees_all_allowed_branches(): void
    {
        $owner = User::factory()->owner()->create();
        $branchA = Branch::factory()->create(['is_active' => true]);
        $branchB = Branch::factory()->create(['is_active' => true]);

        $context = $this->buildContextFor($owner, null);
        $result = $context->build($owner);

        $branchIds = array_column($result['scope']['branches'], 'id');
        $this->assertContains($branchA->id, $branchIds);
        $this->assertContains($branchB->id, $branchIds);
        $this->assertTrue($result['scope']['viewing_all']);
    }

    public function test_manager_only_sees_assigned_branch(): void
    {
        $branchA = Branch::factory()->create(['is_active' => true]);
        $branchB = Branch::factory()->create(['is_active' => true]);

        $manager = User::factory()->manager()->atBranch($branchA)->create();

        $context = $this->buildContextFor($manager, $branchA->id);
        $result = $context->build($manager);

        $branchIds = array_column($result['scope']['branches'], 'id');
        $this->assertContains($branchA->id, $branchIds);
        $this->assertNotContains($branchB->id, $branchIds);
        $this->assertFalse($result['scope']['viewing_all']);
    }

    public function test_manager_does_not_receive_sensitive_financial_fields(): void
    {
        $branch = Branch::factory()->create(['is_active' => true]);
        $manager = User::factory()->manager()->atBranch($branch)->create();

        $context = $this->buildContextFor($manager, $branch->id);
        $result = $context->build($manager);

        $this->assertArrayNotHasKey('inventory_cost', $result['summary']);
        $this->assertArrayNotHasKey('payroll_cost', $result['summary']);
        $this->assertArrayNotHasKey('gross_margin', $result['summary']);
        $this->assertFalse($result['viewer']['may_view_profit']);
    }

    public function test_owner_receives_sensitive_financial_fields(): void
    {
        $branch = Branch::factory()->create(['is_active' => true]);
        $owner = User::factory()->owner()->create();

        $context = $this->buildContextFor($owner, null);
        $result = $context->build($owner);

        $this->assertArrayHasKey('inventory_cost', $result['summary']);
        $this->assertArrayHasKey('payroll_cost', $result['summary']);
        $this->assertArrayHasKey('gross_margin', $result['summary']);
        $this->assertTrue($result['viewer']['may_view_profit']);
    }

    public function test_customer_names_do_not_appear_in_context(): void
    {
        $branch = Branch::factory()->create(['is_active' => true]);
        $owner = User::factory()->owner()->create();
        $customer = Customer::factory()->create(['name' => 'Nguyen Van A']);
        Appointment::factory()->create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'starts_at' => now()->startOfMonth()->copy()->addDay(),
        ]);

        $context = $this->buildContextFor($owner, null);
        $result = $context->build($owner);
        $json = json_encode($result);

        $this->assertStringNotContainsString('Nguyen Van A', $json);
        $this->assertStringNotContainsString('nguyen van a', strtolower($json));
    }

    public function test_customer_phone_do_not_appear_in_context(): void
    {
        $branch = Branch::factory()->create(['is_active' => true]);
        $owner = User::factory()->owner()->create();
        $customer = Customer::factory()->create(['phone' => '0912345678']);
        Appointment::factory()->create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'starts_at' => now()->startOfMonth()->copy()->addDay(),
        ]);

        $context = $this->buildContextFor($owner, null);
        $result = $context->build($owner);
        $json = json_encode($result);

        $this->assertStringNotContainsString('0912345678', $json);
    }

    public function test_customer_email_do_not_appear_in_context(): void
    {
        $branch = Branch::factory()->create(['is_active' => true]);
        $owner = User::factory()->owner()->create();
        $customer = Customer::factory()->create(['email' => 'secret@example.com']);
        Appointment::factory()->create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'starts_at' => now()->startOfMonth()->copy()->addDay(),
        ]);

        $context = $this->buildContextFor($owner, null);
        $result = $context->build($owner);
        $json = json_encode($result);

        $this->assertStringNotContainsString('secret@example.com', $json);
    }

    public function test_appointment_status_counts_match_period_and_branch(): void
    {
        $branch = Branch::factory()->create(['is_active' => true]);
        $otherBranch = Branch::factory()->create(['is_active' => true]);
        $owner = User::factory()->owner()->create();

        Appointment::factory()->count(3)->create([
            'branch_id' => $branch->id,
            'status' => AppointmentStatus::Completed,
            'starts_at' => now()->startOfMonth()->copy()->addDay(),
        ]);
        Appointment::factory()->count(2)->create([
            'branch_id' => $branch->id,
            'status' => AppointmentStatus::Cancelled,
            'starts_at' => now()->startOfMonth()->copy()->addDay(2),
        ]);
        Appointment::factory()->count(5)->create([
            'branch_id' => $otherBranch->id,
            'status' => AppointmentStatus::Completed,
            'starts_at' => now()->startOfMonth()->copy()->addDay(),
        ]);

        $context = $this->buildContextFor($owner, $branch->id);
        $result = $context->build($owner);

        $this->assertEquals(3, $result['appointments_by_status'][AppointmentStatus::Completed->value] ?? 0);
        $this->assertEquals(2, $result['appointments_by_status'][AppointmentStatus::Cancelled->value] ?? 0);
    }

    public function test_top_service_belongs_to_correct_period_and_branch(): void
    {
        $branch = Branch::factory()->create(['is_active' => true]);
        $otherBranch = Branch::factory()->create(['is_active' => true]);
        $owner = User::factory()->owner()->create();

        $invoice = Invoice::factory()->create([
            'branch_id' => $branch->id,
            'status' => InvoiceStatus::Paid,
            'paid_at' => now()->startOfMonth()->copy()->addDay(),
            'total' => 100_000,
        ]);
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'name' => 'Chăm sóc da',
            'line_total' => 100_000,
            'quantity' => 1,
        ]);

        $otherInvoice = Invoice::factory()->create([
            'branch_id' => $otherBranch->id,
            'status' => InvoiceStatus::Paid,
            'paid_at' => now()->startOfMonth()->copy()->addDay(),
            'total' => 500_000,
        ]);
        InvoiceItem::factory()->create([
            'invoice_id' => $otherInvoice->id,
            'name' => 'Massage',
            'line_total' => 500_000,
            'quantity' => 1,
        ]);

        $context = $this->buildContextFor($owner, $branch->id);
        $result = $context->build($owner);

        $this->assertCount(1, $result['top_services']);
        $this->assertEquals('Chăm sóc da', $result['top_services'][0]['name']);
    }

    public function test_low_stock_is_limited_to_branch_scope(): void
    {
        $branchA = Branch::factory()->create(['is_active' => true]);
        $branchB = Branch::factory()->create(['is_active' => true]);
        $owner = User::factory()->owner()->create();

        $productA = Product::factory()->create([
            'name' => 'Kem dưỡng A',
            'minimum_stock' => 100,
            'is_active' => true,
        ]);
        $productB = Product::factory()->create([
            'name' => 'Kem dưỡng B',
            'minimum_stock' => 100,
            'is_active' => true,
        ]);

        // Create inventory at branch A only
        DB::table('branch_products')->insert([
            ['branch_id' => $branchA->id, 'product_id' => $productA->id, 'minimum_stock' => 100, 'is_active' => true],
            ['branch_id' => $branchB->id, 'product_id' => $productB->id, 'minimum_stock' => 100, 'is_active' => true],
        ]);
        // No inventory movements → stock = 0, both are low stock

        $context = $this->buildContextFor($owner, $branchA->id);
        $result = $context->build($owner);

        $productNames = array_column($result['low_stock'], 'product');
        $this->assertContains('Kem dưỡng A', $productNames);
        $this->assertNotContains('Kem dưỡng B', $productNames);
    }

    public function test_context_is_not_widened_by_fake_session_branch(): void
    {
        $branchA = Branch::factory()->create(['is_active' => true]);
        $branchB = Branch::factory()->create(['is_active' => true]);

        // Manager only assigned to branch A
        $manager = User::factory()->manager()->atBranch($branchA)->create();

        // Fake session claiming branch B
        $context = $this->buildContextFor($manager, $branchB->id);
        $result = $context->build($manager);

        // Should fallback to first available branch (A) because B is not in accessible branches
        $branchIds = array_column($result['scope']['branches'], 'id');
        $this->assertNotContains($branchB->id, $branchIds);
        $this->assertContains($branchA->id, $branchIds);
    }

    public function test_default_period_uses_application_timezone(): void
    {
        $branch = Branch::factory()->create(['is_active' => true]);
        $owner = User::factory()->owner()->create();

        $context = $this->buildContextFor($owner, null);
        $result = $context->build($owner);

        $this->assertEquals(config('app.timezone'), $result['timezone']);
        $this->assertNotEmpty($result['period']['from']);
        $this->assertNotEmpty($result['period']['to']);
    }

    public function test_no_data_context_is_still_valid(): void
    {
        $branch = Branch::factory()->create(['is_active' => true]);
        $owner = User::factory()->owner()->create();

        $context = $this->buildContextFor($owner, null);
        $result = $context->build($owner);

        $this->assertArrayHasKey('summary', $result);
        $this->assertArrayHasKey('appointments_by_status', $result);
        $this->assertArrayHasKey('top_services', $result);
        $this->assertArrayHasKey('top_employees', $result);
        $this->assertArrayHasKey('low_stock', $result);
        $this->assertEquals([], $result['top_services']);
    }

    public function test_invoice_question_selects_requested_date_range(): void
    {
        $branch = Branch::factory()->create(['is_active' => true]);
        $manager = User::factory()->manager()->atBranch($branch)->create();

        $context = $this->buildContextFor($manager, $branch->id);
        $result = $context->build($manager, 'Thống kê hóa đơn ngày 15 và 16/9/2026');

        $this->assertSame(['invoices'], $result['query_plan']['domains']);
        $this->assertSame('2026-09-15', $result['query_plan']['period']['from']);
        $this->assertSame('2026-09-16', $result['query_plan']['period']['to']);
        $this->assertArrayHasKey('invoices', $result['data']);
    }

    public function test_invoice_context_is_limited_to_selected_branch(): void
    {
        $branch = Branch::factory()->create(['is_active' => true]);
        $otherBranch = Branch::factory()->create(['is_active' => true]);
        $manager = User::factory()->manager()->atBranch($branch)->create();

        Invoice::factory()->create([
            'branch_id' => $branch->id,
            'number' => 'HD-IN-SCOPE',
            'status' => InvoiceStatus::Paid,
            'total' => 150_000,
            'created_at' => '2026-09-15 10:00:00',
            'paid_at' => '2026-09-15 10:00:00',
        ]);
        Invoice::factory()->create([
            'branch_id' => $otherBranch->id,
            'number' => 'HD-OUT-OF-SCOPE',
            'status' => InvoiceStatus::Paid,
            'total' => 900_000,
            'created_at' => '2026-09-15 11:00:00',
            'paid_at' => '2026-09-15 11:00:00',
        ]);

        $context = $this->buildContextFor($manager, $branch->id);
        $result = $context->build($manager, 'Thống kê hóa đơn ngày 15 và 16/9/2026');
        $json = json_encode($result, JSON_THROW_ON_ERROR);

        $this->assertSame(1, $result['data']['invoices']['created_count']);
        $this->assertStringContainsString('HD-IN-SCOPE', $json);
        $this->assertStringNotContainsString('HD-OUT-OF-SCOPE', $json);
        $this->assertStringNotContainsString('900000', $json);
    }

    public function test_context_role_is_owner_for_owner(): void
    {
        $owner = User::factory()->owner()->create();
        $context = $this->buildContextFor($owner, null);
        $result = $context->build($owner);

        $this->assertEquals(UserRole::Owner->value, $result['viewer']['role']);
    }

    public function test_context_role_is_manager_for_manager(): void
    {
        $branch = Branch::factory()->create(['is_active' => true]);
        $manager = User::factory()->manager()->atBranch($branch)->create();
        $context = $this->buildContextFor($manager, $branch->id);
        $result = $context->build($manager);

        $this->assertEquals(UserRole::Manager->value, $result['viewer']['role']);
    }
}
