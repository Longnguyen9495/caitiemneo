<?php

use App\Enums\GalleryAlbum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mỗi tệp trong album thuộc về một khu của trang công khai.
 *
 * Mặc định là `showcase` nên toàn bộ ảnh đang có giữ nguyên chỗ cũ: chúng là
 * mẫu móng, và trang album vẫn trưng đúng bấy nhiêu tấm sau khi chạy migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gallery_items', function (Blueprint $table): void {
            $table->string('album', 16)->default(GalleryAlbum::Showcase->value)->after('type');

            // Mọi truy vấn công khai đều hỏi "tệp của khu này, loại này, mới
            // nhất trước", nên chỉ mục đi đúng theo thứ tự đó.
            $table->index(['album', 'type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('gallery_items', function (Blueprint $table): void {
            $table->dropIndex(['album', 'type', 'created_at']);
            $table->dropColumn('album');
        });
    }
};
