<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Things worth a second look, and what somebody decided about them.
 *
 * The audit trail says what happened; this table says which of it looked odd
 * and whether a human has since accepted or rejected that judgement. Keeping
 * the review outcome is the point: a flag nobody ever closes is noise, and
 * noise is what makes people stop reading the list at all.
 *
 * Flags are derived from `audit_events` and are therefore reproducible — the
 * table is a cache of judgement, never the source of truth about what happened.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risk_flags', function (Blueprint $table): void {
            $table->id();

            // Quy tắc đã sinh ra cờ này, ví dụ `self_recorded_attendance`.
            $table->string('rule', 60);
            $table->string('severity', 20);

            // Chủ thể lưu lỏng giống `audit_events`: cờ phải sống lâu hơn dòng
            // dữ liệu mà nó nói tới, nếu không thì xóa bản ghi là xóa luôn nghi vấn.
            $table->string('subject_type', 60)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();

            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name')->nullable();

            // Câu giải thích cho người đọc, đã viết sẵn để hàng đợi không phải
            // tự dựng lại ngữ cảnh của từng quy tắc.
            $table->string('summary', 500);
            $table->json('context')->nullable();

            $table->timestamp('detected_at');

            // Kết luận của con người.
            $table->string('review_status', 20)->default('open');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note', 500)->nullable();

            $table->timestamps();

            // Chạy lại bộ dò không được nhân bản cờ cũ.
            $table->unique(['rule', 'subject_type', 'subject_id'], 'risk_flags_rule_subject_unique');

            $table->index(['branch_id', 'review_status', 'detected_at'], 'risk_flags_branch_status_index');
            $table->index(['actor_id', 'detected_at'], 'risk_flags_actor_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_flags');
    }
};
