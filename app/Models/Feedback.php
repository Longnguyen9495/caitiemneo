<?php

namespace App\Models;

use App\Enums\FeedbackStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Một lời nhận xét khách gửi cho tiệm.
 *
 * Chỉ những bản đã duyệt mới ra trang công khai, nên mọi truy vấn cho trang đó
 * đều phải đi qua scope `published`.
 */
#[Fillable([
    'author_name',
    'rating',
    'content',
    'status',
    'published_at',
    'reviewed_by',
])]
class Feedback extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'status' => FeedbackStatus::class,
            'published_at' => 'datetime',
        ];
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Feedback đang hiện trên trang công khai, mới đăng đứng trước.
     *
     * Kèm khoá chính để hai bản duyệt trong cùng một giây vẫn có thứ tự cố định.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', FeedbackStatus::Published)
            ->orderByDesc('published_at')
            ->orderByDesc('id');
    }

    /** Hàng đợi chờ người duyệt, cái gửi sớm nhất xử lý trước. */
    public function scopeAwaitingReview(Builder $query): Builder
    {
        return $query->where('status', FeedbackStatus::Pending)->orderBy('created_at');
    }

    public function isPublished(): bool
    {
        return $this->status === FeedbackStatus::Published;
    }
}
