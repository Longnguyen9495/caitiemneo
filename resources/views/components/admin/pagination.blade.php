@props(['paginator'])

@if ($paginator->hasPages())
    <nav class="admin-pagination" role="navigation" aria-label="Phân trang">
        <p>
            Hiển thị {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }}
            trên {{ $paginator->total() }} bản ghi
        </p>
        <div class="admin-pagination-links">
            @if ($paginator->onFirstPage())
                <span aria-disabled="true">← Trước</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev">← Trước</a>
            @endif

            <span class="admin-pagination-current">Trang {{ $paginator->currentPage() }}/{{ $paginator->lastPage() }}</span>

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next">Sau →</a>
            @else
                <span aria-disabled="true">Sau →</span>
            @endif
        </div>
    </nav>
@endif
