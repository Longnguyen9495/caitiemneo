<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mẫu móng khách chọn trong album lúc đặt lịch.
 *
 * Lưu bằng khóa ngoại chứ không chép đường dẫn ảnh vào ghi chú: thợ mở lịch
 * hẹn là thấy đúng tấm ảnh khách trỏ vào, và ảnh có bị gỡ khỏi album thì lịch
 * hẹn vẫn còn, chỉ mất phần tham chiếu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->foreignId('gallery_item_id')->nullable()->after('employee_id')
                ->constrained('gallery_items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('gallery_item_id');
        });
    }
};
