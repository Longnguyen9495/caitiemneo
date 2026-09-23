<?php

namespace Tests\Feature\Shifts;

use App\Enums\ShiftRequestStatus;
use App\Models\Branch;
use App\Models\ShiftAssignment;
use App\Models\ShiftRequest;
use App\Models\User;
use App\Models\WorkShift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Huy hiệu trên menu và bộ lọc trên trang phải nói cùng một thứ.
 *
 * Trước đây huy hiệu đếm ba đơn cần xử lý rồi dẫn tới một danh sách xếp theo
 * ngày gửi, không lọc được gì — ba đơn ấy nằm lẫn giữa đơn đã hủy và đã duyệt
 * xong từ tháng trước.
 */
class ShiftRequestWorklistTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private User $manager;

    private User $employee;

    private WorkShift $shift;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create();
        $this->manager = User::factory()->manager()->withoutBranch()->atBranch($this->branch)->create();
        $this->employee = User::factory()->employee()->withoutBranch()->atBranch($this->branch)->create();
        $this->shift = WorkShift::factory()->atBranch($this->branch)->create();
    }

    public function test_the_need_action_filter_hides_settled_requests(): void
    {
        $pending = $this->leaveRequest('2026-10-10', ShiftRequestStatus::PendingApproval);
        $cancelled = $this->leaveRequest('2026-10-11', ShiftRequestStatus::Cancelled);

        $response = $this->actingAs($this->manager)
            ->get(route('admin.shift-requests.index', ['loc' => 'can-xu-ly']))
            ->assertOk();

        $response->assertSee($pending->work_date->format('d/m/Y'));
        $response->assertDontSee($cancelled->work_date->format('d/m/Y'));
    }

    /** Không lọc thì vẫn thấy toàn bộ sổ như trước. */
    public function test_the_unfiltered_board_still_shows_everything(): void
    {
        $pending = $this->leaveRequest('2026-10-10', ShiftRequestStatus::PendingApproval);
        $cancelled = $this->leaveRequest('2026-10-11', ShiftRequestStatus::Cancelled);

        $this->actingAs($this->manager)
            ->get(route('admin.shift-requests.index'))
            ->assertOk()
            ->assertSee($pending->work_date->format('d/m/Y'))
            ->assertSee($cancelled->work_date->format('d/m/Y'));
    }

    /**
     * Đơn nghỉ đã duyệt mà chưa có người thay vẫn là việc của quản lý — huy
     * hiệu đếm nó, nên bộ lọc cũng phải giữ nó lại.
     */
    public function test_an_approved_leave_without_a_stand_in_counts_as_needing_action(): void
    {
        $approved = $this->leaveRequest('2026-10-12', ShiftRequestStatus::Approved);

        $this->actingAs($this->manager)
            ->get(route('admin.shift-requests.index', ['loc' => 'can-xu-ly']))
            ->assertOk()
            ->assertSee($approved->work_date->format('d/m/Y'));
    }

    /** Huy hiệu dẫn thẳng vào danh sách đã lọc chứ không phải trang trống lọc. */
    public function test_the_menu_badge_links_to_the_filtered_list(): void
    {
        $this->leaveRequest('2026-10-10', ShiftRequestStatus::PendingApproval);

        $this->actingAs($this->manager)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(route('admin.shift-requests.index', ['loc' => 'can-xu-ly']), false);
    }

    public function test_the_filter_survives_paging(): void
    {
        $this->actingAs($this->manager)
            ->get(route('admin.shift-requests.index', ['loc' => 'dang-cho']))
            ->assertOk()
            ->assertSee('value="dang-cho" selected', false);
    }

    /** Duyệt đơn đụng tiền và không hoàn tác được, nên nút phải hỏi lại. */
    public function test_deciding_a_request_asks_for_confirmation(): void
    {
        $this->leaveRequest('2026-10-10', ShiftRequestStatus::PendingApproval);

        $this->actingAs($this->manager)
            ->get(route('admin.shift-requests.index'))
            ->assertOk()
            ->assertSee('data-neo-confirm="Duyệt đơn', false)
            ->assertSee('không hoàn tác được', false);
    }

    private function leaveRequest(string $date, ShiftRequestStatus $status): ShiftRequest
    {
        $assignment = ShiftAssignment::factory()
            ->forEmployee($this->employee)
            ->atBranch($this->branch)
            ->usingShift($this->shift)
            ->on($date)
            ->create();

        return ShiftRequest::factory()->forAssignment($assignment)->leave()->create(['status' => $status]);
    }
}
