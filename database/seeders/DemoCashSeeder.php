<?php

namespace Database\Seeders;

use App\Actions\Cash\RecordCashTransactionAction;
use App\Enums\CashTransactionCategory;
use App\Enums\CashTransactionType;
use App\Enums\PaymentMethod;
use App\Models\Branch;
use App\Models\CashTransaction;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;

/**
 * Các khoản chi thu ngoài hóa đơn: thuê nhà, điện nước, quảng cáo, nhập vật tư.
 *
 * Doanh thu dịch vụ và chi lương KHÔNG nằm ở đây — hai khoản đó do hóa đơn và
 * bảng lương tự sinh ra, ghi tay lần nữa là đếm đúp tiền.
 *
 * Mỗi khoản mang một số chứng từ cố định nên chạy lại seeder là bỏ qua, không
 * cộng thêm chi phí vào cùng một tháng.
 */
class DemoCashSeeder extends Seeder
{
    public function __construct(private RecordCashTransactionAction $recordTransaction) {}

    public function run(): void
    {
        $actor = DemoData::actor();
        $created = 0;

        foreach (DemoData::branches() as $branch) {
            for ($month = DemoData::start()->copy(); $month->lte(DemoData::today()); $month->addMonthNoOverflow()) {
                foreach ($this->monthlyEntries($branch, $month) as $entry) {
                    $created += $this->record($entry, $actor) ? 1 : 0;
                }
            }
        }

        $this->command?->line(sprintf('  Đã ghi %d phiếu thu chi thủ công.', $created));
    }

    /**
     * Một tháng vận hành của một cơ sở.
     *
     * @return array<int, array<string, mixed>>
     */
    private function monthlyEntries(Branch $branch, CarbonInterface $month): array
    {
        $tag = $branch->code.'-'.$month->format('Ym');

        return [
            [
                'branch_id' => $branch->getKey(),
                'type' => CashTransactionType::Expense,
                'category' => CashTransactionCategory::Rent,
                'amount' => $branch->code === 'CN-01' ? 24_000_000 : 18_000_000,
                'payment_method' => PaymentMethod::Transfer,
                'reference' => 'THUE-'.$tag,
                'note' => 'Tiền thuê mặt bằng tháng '.$month->format('m/Y'),
                'occurred_at' => $month->copy()->startOfMonth()->setTime(9, 0),
            ],
            [
                'branch_id' => $branch->getKey(),
                'type' => CashTransactionType::Expense,
                'category' => CashTransactionCategory::Utilities,
                'amount' => fake()->numberBetween(180, 340) * 10_000,
                'payment_method' => PaymentMethod::Transfer,
                'reference' => 'DIENNUOC-'.$tag,
                'note' => 'Điện, nước, internet tháng '.$month->format('m/Y'),
                'occurred_at' => $month->copy()->startOfMonth()->addDays(5)->setTime(10, 30),
            ],
            [
                'branch_id' => $branch->getKey(),
                'type' => CashTransactionType::Expense,
                'category' => CashTransactionCategory::Marketing,
                'amount' => fake()->numberBetween(120, 260) * 10_000,
                'payment_method' => PaymentMethod::Ewallet,
                'reference' => 'QUANGCAO-'.$tag,
                'note' => 'Chạy quảng cáo Facebook và tặng voucher khách mới',
                'occurred_at' => $month->copy()->startOfMonth()->addDays(9)->setTime(14, 0),
            ],
            [
                'branch_id' => $branch->getKey(),
                'type' => CashTransactionType::Expense,
                'category' => CashTransactionCategory::Inventory,
                'amount' => fake()->numberBetween(300, 620) * 10_000,
                'payment_method' => PaymentMethod::Cash,
                'reference' => 'VATTU-'.$tag,
                'note' => 'Thanh toán đơn nhập vật tư trong tháng',
                'occurred_at' => $month->copy()->startOfMonth()->addDays(12)->setTime(16, 45),
            ],
            [
                'branch_id' => $branch->getKey(),
                'type' => CashTransactionType::Income,
                'category' => CashTransactionCategory::OtherIncome,
                'amount' => fake()->numberBetween(60, 180) * 10_000,
                'payment_method' => PaymentMethod::Cash,
                'reference' => 'BANLE-'.$tag,
                'note' => 'Bán lẻ dũa, sơn mang về cho khách',
                'occurred_at' => $month->copy()->startOfMonth()->addDays(18)->setTime(19, 20),
            ],
        ];
    }

    /**
     * Ghi một phiếu, bỏ qua nếu số chứng từ đó đã có.
     *
     * @param  array<string, mixed>  $entry
     */
    private function record(array $entry, User $actor): bool
    {
        if (CashTransaction::query()->where('reference', $entry['reference'])->exists()) {
            return false;
        }

        $this->recordTransaction->handle($entry, $actor);

        return true;
    }
}
