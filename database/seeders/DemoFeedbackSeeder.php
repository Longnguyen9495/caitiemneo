<?php

namespace Database\Seeders;

use App\Enums\FeedbackStatus;
use App\Models\Feedback;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;

/**
 * Feedback khách cho khu "Khách nói gì" trên trang chủ.
 *
 * Có cả một bản đang chờ duyệt, để màn hình duyệt trong khu quản trị cũng có
 * việc mà xem — một hàng đợi rỗng không cho biết nó trông thế nào lúc có việc.
 *
 * Khoá tự nhiên là tên người gửi kèm nội dung, nên chạy lại seeder là bỏ qua
 * chứ không nhân bản feedback.
 */
class DemoFeedbackSeeder extends Seeder
{
    public function run(): void
    {
        $created = 0;

        foreach ($this->entries() as $entry) {
            $feedback = Feedback::query()->firstOrCreate([
                'author_name' => $entry['author_name'],
                'content' => $entry['content'],
            ], [
                'rating' => $entry['rating'],
                'status' => $entry['status'],
                'published_at' => $entry['status'] === FeedbackStatus::Published ? $entry['sent_at'] : null,
            ]);

            if (! $feedback->wasRecentlyCreated) {
                continue;
            }

            // `created_at` là lúc khách gửi, đặt lại sau khi tạo để danh sách
            // demo không dồn hết vào một phút.
            $feedback->forceFill(['created_at' => $entry['sent_at']])->save();
            $created++;
        }

        $this->command?->line(sprintf('  Đã ghi %d feedback của khách.', $created));
    }

    /**
     * @return array<int, array{author_name: string, rating: int, content: string, status: FeedbackStatus, sent_at: CarbonInterface}>
     */
    private function entries(): array
    {
        $today = DemoData::today();

        return [
            [
                'author_name' => 'Chị Linh',
                'rating' => 5,
                'content' => 'Mình mang ảnh mẫu tới, bạn thợ làm giống gần như y hệt. Móng gọn, không đau khi lấy da.',
                'status' => FeedbackStatus::Published,
                'sent_at' => $today->copy()->subDays(26)->setTime(20, 12),
            ],
            [
                'author_name' => 'Hà Trang',
                'rating' => 5,
                'content' => 'Tiệm nhỏ mà sạch, nhạc dễ chịu. Đặt lịch online xong tiệm gọi lại xác nhận luôn nên không phải chờ.',
                'status' => FeedbackStatus::Published,
                'sent_at' => $today->copy()->subDays(18)->setTime(13, 40),
            ],
            [
                'author_name' => 'Ngọc Anh',
                'rating' => 4,
                'content' => 'Mẫu vẽ rất xinh, giá đúng như bảng giá trên trang. Chỉ là hôm mình đến hơi đông nên phải đợi thêm một chút.',
                'status' => FeedbackStatus::Published,
                'sent_at' => $today->copy()->subDays(9)->setTime(17, 5),
            ],
            [
                'author_name' => 'Chị Mai',
                'rating' => 5,
                'content' => 'Đi làm móng ở đây lần thứ tư rồi. Được cái là bạn thợ nói trước nếu mẫu mình chọn không bền với tay mình.',
                'status' => FeedbackStatus::Published,
                'sent_at' => $today->copy()->subDays(4)->setTime(11, 20),
            ],
            [
                'author_name' => 'Thu Hằng',
                'rating' => 5,
                'content' => 'Hôm qua mình làm ombre sữa, tới giờ vẫn chưa thấy bong chỗ nào. Cảm ơn tiệm nhé!',
                'status' => FeedbackStatus::Pending,
                'sent_at' => $today->copy()->subDay()->setTime(21, 48),
            ],
        ];
    }
}
