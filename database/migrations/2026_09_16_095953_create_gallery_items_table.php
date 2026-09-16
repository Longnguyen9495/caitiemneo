<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Album ảnh và video trưng trên trang công khai.
     *
     * `path` là đường dẫn tính từ gốc `public`, dùng được cho cả tệp tiệm tự
     * tải lên (`storage/gallery/...`) lẫn bộ ảnh nén sẵn có từ trước
     * (`images/products/web/...`), nên không phải chép tệp cũ đi đâu cả.
     */
    public function up(): void
    {
        Schema::create('gallery_items', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 16);
            $table->string('path');
            // Ảnh có nhiều khổ để trình duyệt tự chọn; video chỉ có một tệp.
            $table->json('sources')->nullable();
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->string('original_name');
            $table->unsignedBigInteger('byte_size')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Trang công khai luôn hỏi "ảnh mới nhất của loại này", nên đánh chỉ
            // mục đúng theo thứ tự đó.
            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gallery_items');
    }
};
