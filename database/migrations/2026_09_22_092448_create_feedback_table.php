<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feedback khách gửi từ trang công khai.
 *
 * Tên bảng là `feedback` vì "feedback" không có dạng số nhiều — đó cũng là tên
 * Eloquent tự suy ra cho model `Feedback`, nên không phải khai báo thêm.
 *
 * Không lưu số điện thoại hay email: biểu mẫu chỉ hỏi ba ô để khách gõ nhanh
 * trên điện thoại, và dữ liệu không cần thì không nên nằm trong cơ sở dữ liệu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback', function (Blueprint $table): void {
            $table->id();
            $table->string('author_name');
            $table->unsignedTinyInteger('rating');
            $table->text('content');
            $table->string('status', 16);
            // Mốc đăng lên trang, tách khỏi `created_at` là mốc khách gửi: một
            // feedback gửi hôm qua mà duyệt hôm nay vẫn phải đứng đầu danh sách
            // theo ngày nó thực sự xuất hiện.
            $table->timestamp('published_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Trang chủ luôn hỏi "feedback đang hiện, mới nhất trước"; khu quản
            // trị hỏi "cái nào đang chờ". Một chỉ mục phục vụ cả hai.
            $table->index(['status', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback');
    }
};
