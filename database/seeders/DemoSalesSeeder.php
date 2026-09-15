<?php

namespace Database\Seeders;

use App\Actions\Invoices\CancelInvoiceAction;
use App\Actions\Invoices\ConvertAppointmentToInvoiceAction;
use App\Actions\Invoices\PayInvoiceAction;
use App\Actions\Invoices\RecalculateInvoiceAction;
use App\Enums\AppointmentStatus;
use App\Enums\PaymentMethod;
use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\Branch;
use App\Models\BranchService;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\ShiftAssignment;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Khách, lịch hẹn và hóa đơn của hai tháng gần nhất.
 *
 * Hóa đơn không được ghi thẳng: lịch hẹn đã xong đi qua
 * {@see ConvertAppointmentToInvoiceAction} rồi {@see PayInvoiceAction}, nên
 * hoa hồng, sổ quỹ và mốc `paid_at` khớp đúng như hàng thật — đó cũng là cái
 * bảng lương demo dựa vào để ra số.
 *
 * Thợ đứng tên hóa đơn luôn là người có ca tại chính cơ sở đó trong ngày đó.
 */
class DemoSalesSeeder extends Seeder
{
    /** Ảnh chứng từ dùng chung cho mọi hóa đơn đã thanh toán. */
    private const BILL_IMAGE_PATH = 'bills/demo/chung-tu-demo.svg';

    public function __construct(
        private ConvertAppointmentToInvoiceAction $convert,
        private PayInvoiceAction $pay,
        private CancelInvoiceAction $cancel,
        private RecalculateInvoiceAction $recalculate,
    ) {}

    public function run(): void
    {
        $branches = DemoData::branches();
        $customers = $this->customers();
        $menus = $this->menus($branches);

        if ($menus->isEmpty()) {
            $this->command?->warn('  Chi nhánh chưa có bảng giá, bỏ qua lịch hẹn demo.');

            return;
        }

        $this->billImage();
        $this->appointments($branches, $customers, $menus);
        $this->invoices();
    }

    /**
     * Sổ khách quen.
     *
     * Số điện thoại sinh theo chỉ số chứ không random, vì đó là khoá tự nhiên
     * để chạy lại seeder không đẻ thêm khách trùng tên.
     *
     * @return Collection<int, Customer>
     */
    private function customers(): Collection
    {
        $names = [
            'Nguyễn Thị Hồng Nhung', 'Trần Khánh Ly', 'Lê Thu Trang', 'Phạm Minh Châu', 'Hoàng Thanh Hương',
            'Vũ Diệu Linh', 'Đặng Phương Thảo', 'Bùi Ngọc Ánh', 'Đỗ Hà My', 'Ngô Thùy Dương',
            'Dương Kim Chi', 'Lý Bảo Trâm', 'Phan Tuyết Mai', 'Trịnh Hải Anh', 'Đinh Lan Phương',
            'Cao Mỹ Duyên', 'Tạ Quỳnh Như', 'Lưu Thảo Vy', 'Hồ Nhật Lệ', 'Mai Khánh Huyền',
            'Nguyễn Gia Hân', 'Trần Bích Ngọc', 'Lê Yến Nhi', 'Phạm Thu Hằng', 'Hoàng Diễm Quỳnh',
            'Vũ Hoài Thương', 'Đặng Thanh Tâm', 'Bùi Phương Uyên', 'Đỗ Kiều Trinh', 'Ngô Lê Na',
            'Chu Minh Thư', 'Hà Tường Vi', 'Võ Ngọc Bích', 'Lâm Tuệ Nhi', 'Đoàn Hạ Vy',
            'Tống Mai Anh', 'Kiều Thanh Trúc', 'Lại Bảo Châu', 'Quách Hồng Ngọc', 'Tô Ánh Nguyệt',
        ];

        return collect($names)->values()->map(fn (string $name, int $index): Customer => Customer::query()->firstOrCreate(
            ['phone' => '09'.str_pad((string) (12_000_000 + $index * 7), 8, '0', STR_PAD_LEFT)],
            [
                'name' => $name,
                'email' => null,
                'note' => $index % 9 === 0 ? 'Khách quen, thích tông nude' : null,
            ],
        ));
    }

    /**
     * Bảng giá đang bán của từng cơ sở.
     *
     * @param  Collection<int, Branch>  $branches
     * @return Collection<int, Collection<int, BranchService>>
     */
    private function menus(Collection $branches): Collection
    {
        return $branches
            ->mapWithKeys(fn (Branch $branch): array => [
                $branch->getKey() => BranchService::query()
                    ->with('service')
                    ->where('branch_id', $branch->getKey())
                    ->where('is_active', true)
                    ->orderBy('id')
                    ->get(),
            ])
            ->reject(fn (Collection $menu): bool => $menu->isEmpty());
    }

    /**
     * Lịch hẹn rải đều khoảng demo, trạng thái theo vị trí so với hôm nay.
     *
     * @param  Collection<int, Branch>  $branches
     * @param  Collection<int, Customer>  $customers
     * @param  Collection<int, Collection<int, BranchService>>  $menus
     */
    private function appointments(Collection $branches, Collection $customers, Collection $menus): void
    {
        $rosters = $this->rosters();
        $created = 0;
        $sequence = 0;

        for ($date = DemoData::start()->copy(); $date->lte(DemoData::end()); $date->addDay()) {
            if (! DemoData::isOpen($date)) {
                continue;
            }

            foreach ($branches as $branch) {
                $menu = $menus->get($branch->getKey());

                if ($menu === null) {
                    continue;
                }

                $staffOnDuty = $rosters->get($date->toDateString().'#'.$branch->getKey(), collect());
                $day = $date->toDateString().'#'.$branch->getKey();

                foreach (range(1, 3 + $this->roll($day) % 4) as $slot) {
                    $sequence++;
                    $startsAt = $date->copy()->setTime(9, 30)
                        ->addMinutes(($slot - 1) * 105 + $this->roll($day.'#'.$slot) % 26);

                    if ($this->book($branch, $customers, $menu, $staffOnDuty, $startsAt, $sequence)) {
                        $created++;
                    }
                }
            }
        }

        $this->command?->line(sprintf('  Đã tạo %d lịch hẹn.', $created));
    }

    /**
     * Ai có ca tại cơ sở nào trong ngày nào.
     *
     * Nạp một lần rồi tra trong bộ nhớ, vì vòng lặp đặt lịch hỏi câu này vài
     * nghìn lần.
     *
     * @return Collection<string, Collection<int, int>>
     */
    private function rosters(): Collection
    {
        return ShiftAssignment::query()
            ->whereDate('work_date', '>=', DemoData::start()->toDateString())
            ->whereDate('work_date', '<=', DemoData::end()->toDateString())
            ->get(['employee_id', 'branch_id', 'work_date'])
            ->groupBy(fn (ShiftAssignment $assignment): string => $assignment->work_date->toDateString().'#'.$assignment->branch_id)
            ->map(fn (Collection $group): Collection => $group->pluck('employee_id')->unique()->values());
    }

    /**
     * Một lịch hẹn với vài dịch vụ đi kèm.
     *
     * @param  Collection<int, Customer>  $customers
     * @param  Collection<int, BranchService>  $menu
     * @param  Collection<int, int>  $staffOnDuty
     * @return bool ghi mới hay đã có sẵn
     */
    private function book(
        Branch $branch,
        Collection $customers,
        Collection $menu,
        Collection $staffOnDuty,
        CarbonInterface $startsAt,
        int $sequence,
    ): bool {
        $customer = $customers[$sequence % $customers->count()];
        $lines = $this->pickServices($menu, $sequence);
        $duration = max(30, (int) $lines->sum(fn (BranchService $line): int => (int) ($line->duration_minutes ?? 45)));

        $appointment = Appointment::query()->firstOrNew([
            'branch_id' => $branch->getKey(),
            'starts_at' => $startsAt,
            'customer_phone' => $customer->phone,
        ]);

        if ($appointment->exists) {
            return false;
        }

        $bookedAt = $startsAt->copy()->subDays(2);

        // Ngày tạo phải đi cùng lệnh thêm mới chứ không sửa lại sau, vì cột
        // `appointments.starts_at` đang mang `ON UPDATE CURRENT_TIMESTAMP`:
        // mỗi lần cập nhật hàng đó là giờ hẹn bị kéo về hiện tại.
        $appointment->fill([
            'customer_id' => $customer->getKey(),
            'employee_id' => $staffOnDuty->isEmpty() ? null : $staffOnDuty[$sequence % $staffOnDuty->count()],
            'customer_name' => $customer->name,
            'ends_at' => $startsAt->copy()->addMinutes($duration),
            'duration_minutes' => $duration,
            'status' => $this->appointmentStatus($startsAt, $sequence),
            'note' => $sequence % 11 === 0 ? 'Khách hẹn giờ, đến muộn báo trước' : null,
        ])->forceFill(['created_at' => $bookedAt, 'updated_at' => $bookedAt])->save();

        foreach ($lines->values() as $position => $line) {
            AppointmentService::query()->create([
                'appointment_id' => $appointment->getKey(),
                'service_id' => $line->service_id,
                'price' => $this->price($line, $sequence + $position),
            ]);
        }

        return true;
    }

    /**
     * Một tới ba dịch vụ trong bảng giá của cơ sở.
     *
     * Chọn theo chỉ số thay vì `random()`, vì random của PHP không đi theo hạt
     * giống của faker: chỉ cần nó khác đi một lần là thời lượng khác, giờ hẹn
     * khác, và lần chạy sau sinh ra một bộ lịch hẹn mới thay vì nhận ra bộ cũ.
     *
     * @param  Collection<int, BranchService>  $menu
     * @return Collection<int, BranchService>
     */
    private function pickServices(Collection $menu, int $sequence): Collection
    {
        $rows = $menu->values();
        $count = min($rows->count(), 1 + $this->roll('lines#'.$sequence) % 3);
        $first = $this->roll('menu#'.$sequence);

        return collect(range(0, $count - 1))
            ->map(fn (int $offset): BranchService => $rows[($first + $offset * 37) % $rows->count()])
            ->unique('id')
            ->values();
    }

    /**
     * Con số tất định cho một khoá bất kỳ.
     *
     * Cùng cách làm với lệnh `attendance:demo`: chạy lại phải ra đúng dữ liệu
     * cũ thì seeder mới nhận ra được cái nó đã tạo lần trước.
     */
    private function roll(string $key): int
    {
        return (int) (crc32($key) % 1000);
    }

    /**
     * Giá chốt của một dòng dịch vụ.
     *
     * Dịch vụ bán theo khoảng thì thợ chốt trong khoảng đó, làm tròn nghìn để
     * số tiền trông giống hóa đơn thật.
     */
    private function price(BranchService $line, int $sequence): float
    {
        if (! $line->hasPriceRange()) {
            return (float) $line->price;
        }

        $floor = (int) ceil((int) $line->price_min / 1000);
        $ceiling = (int) floor((int) $line->price_max / 1000);
        $span = max(1, $ceiling - $floor + 1);

        return (float) (($floor + $this->roll('price#'.$sequence.'#'.$line->getKey()) % $span) * 1000);
    }

    /** Quá khứ thì phần lớn đã xong; hôm nay và mai thì còn đang chạy. */
    private function appointmentStatus(CarbonInterface $startsAt, int $sequence): AppointmentStatus
    {
        if ($startsAt->isFuture()) {
            return $sequence % 3 === 0 ? AppointmentStatus::Pending : AppointmentStatus::Confirmed;
        }

        if ($startsAt->isToday()) {
            return $sequence % 2 === 0 ? AppointmentStatus::CheckedIn : AppointmentStatus::Completed;
        }

        return match (true) {
            $sequence % 19 === 0 => AppointmentStatus::NoShow,
            $sequence % 13 === 0 => AppointmentStatus::Cancelled,
            default => AppointmentStatus::Completed,
        };
    }

    /**
     * Mỗi lịch hẹn đã hoàn thành sinh một hóa đơn, phần lớn đã thu tiền.
     *
     * Vài hóa đơn cố ý để lại ở trạng thái nháp hoặc đã hủy, vì màn hình hóa
     * đơn và sổ quỹ chỉ nói lên điều gì đó khi có đủ cả ba trạng thái.
     */
    private function invoices(): void
    {
        $actor = DemoData::actor();

        $appointments = Appointment::query()
            ->where('status', AppointmentStatus::Completed)
            ->whereDoesntHave('invoice')
            ->whereDate('starts_at', '<=', DemoData::today()->toDateString())
            ->orderBy('starts_at')
            ->get();

        $counts = ['paid' => 0, 'draft' => 0, 'cancelled' => 0];

        foreach ($appointments as $index => $appointment) {
            $invoice = $this->convert->handle($appointment, $actor);
            $this->backdate($invoice, $appointment->ends_at);
            $this->discount($invoice, $index);

            if ($index % 17 === 0) {
                $counts['draft']++;

                continue;
            }

            $settledAt = $appointment->ends_at->copy()->addMinutes(5 + $this->roll('settle#'.$appointment->getKey()) % 36);

            try {
                $this->pay->handle($invoice, $actor, $this->paymentMethod($index), $settledAt, self::BILL_IMAGE_PATH);
            } catch (ValidationException) {
                continue;
            }

            if ($index % 29 === 0) {
                $this->cancel->handle($invoice, $actor, 'Khách đổi ý ngay sau khi thanh toán, đã hoàn tiền');
                $counts['cancelled']++;

                continue;
            }

            $counts['paid']++;
        }

        $this->command?->line(sprintf(
            '  Đã lập hóa đơn: %d đã thu, %d còn nháp, %d đã hủy.',
            $counts['paid'], $counts['draft'], $counts['cancelled'],
        ));
    }

    /** Thỉnh thoảng bớt cho khách quen một chút. */
    private function discount(Invoice $invoice, int $index): void
    {
        if ($index % 7 !== 0) {
            return;
        }

        $invoice->forceFill(['discount' => (2 + $this->roll('discount#'.$invoice->getKey()) % 4) * 10_000])->save();
        $this->recalculate->handle($invoice);
    }

    private function paymentMethod(int $index): PaymentMethod
    {
        return match ($index % 5) {
            0, 1 => PaymentMethod::Cash,
            2, 3 => PaymentMethod::Transfer,
            default => PaymentMethod::Ewallet,
        };
    }

    /**
     * Kéo ngày tạo về đúng thời điểm việc đó xảy ra.
     *
     * Các màn hình lọc theo `created_at`, nên nếu để nguyên giờ chạy seeder thì
     * hai tháng dữ liệu sẽ dồn hết vào hôm nay.
     */
    private function backdate(Model $model, CarbonInterface $at): void
    {
        $model->forceFill(['created_at' => $at, 'updated_at' => $at])->saveQuietly();
    }

    /**
     * Ảnh chứng từ giả để hóa đơn đã thu có cái để mở ra xem.
     *
     * {@see PayInvoiceAction} bắt buộc phải có đường dẫn ảnh, nên không thể bỏ
     * trống; đây là một file SVG tự vẽ, không phải ảnh chụp thật.
     */
    private function billImage(): void
    {
        $disk = Storage::disk('public');

        if ($disk->exists(self::BILL_IMAGE_PATH)) {
            return;
        }

        $disk->put(self::BILL_IMAGE_PATH, <<<'SVG'
            <svg xmlns="http://www.w3.org/2000/svg" width="360" height="480" viewBox="0 0 360 480">
              <rect width="360" height="480" fill="#f6f2ee"/>
              <rect x="24" y="24" width="312" height="432" rx="12" fill="#ffffff" stroke="#d8cec4"/>
              <text x="180" y="96" text-anchor="middle" font-family="sans-serif" font-size="20" fill="#3d332b">CHỨNG TỪ DEMO</text>
              <text x="180" y="132" text-anchor="middle" font-family="sans-serif" font-size="13" fill="#8a7d70">Ảnh minh hoạ của dữ liệu mẫu</text>
              <g stroke="#e3dad0" stroke-width="2">
                <line x1="64" y1="180" x2="296" y2="180"/>
                <line x1="64" y1="220" x2="296" y2="220"/>
                <line x1="64" y1="260" x2="296" y2="260"/>
                <line x1="64" y1="300" x2="296" y2="300"/>
              </g>
              <text x="180" y="400" text-anchor="middle" font-family="sans-serif" font-size="12" fill="#b0a396">Không phải chứng từ thật</text>
            </svg>
            SVG);
    }
}
