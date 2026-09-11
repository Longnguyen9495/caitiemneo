<?php

use App\Enums\ServiceCategory;
use App\Enums\ServiceUnit;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nhóm dịch vụ, đơn vị tính và khoảng giá — ba thứ bảng giá giấy có mà cơ sở
 * dữ liệu chưa có.
 *
 * Cột `price` cũ giữ nguyên và vẫn là giá mặc định điền sẵn lên hóa đơn.
 * `price_min`/`price_max` là tùy chọn: để trống nghĩa là dịch vụ có giá cố
 * định, có giá trị nghĩa là thợ được chốt trong khoảng đó tùy độ khó.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->string('category', 30)->default(ServiceCategory::Other->value)->after('description');
            $table->string('unit', 20)->default(ServiceUnit::Set->value)->after('category');
            $table->decimal('price_min', 14, 2)->nullable()->after('price');
            $table->decimal('price_max', 14, 2)->nullable()->after('price_min');
            $table->unsignedSmallInteger('display_order')->default(0)->after('price_max');

            $table->index(['category', 'display_order'], 'services_category_order_index');
        });

        Schema::table('branch_services', function (Blueprint $table): void {
            // Khoảng giá có thể khác nhau giữa các cơ sở, nên nó đi kèm giá
            // của từng chi nhánh chứ không chỉ nằm ở danh mục chung.
            $table->decimal('price_min', 14, 2)->nullable()->after('price');
            $table->decimal('price_max', 14, 2)->nullable()->after('price_min');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->dropIndex('services_category_order_index');
            $table->dropColumn(['category', 'unit', 'price_min', 'price_max', 'display_order']);
        });

        Schema::table('branch_services', function (Blueprint $table): void {
            $table->dropColumn(['price_min', 'price_max']);
        });
    }
};
