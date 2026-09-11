@props(['paginator'])

@if ($paginator->hasPages())
    <nav class="d-flex flex-wrap align-items-center justify-content-between gap-2 px-3 py-3 border-top" aria-label="Phân trang">
        <p class="mb-0 small text-body-secondary">
            {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} trên {{ $paginator->total() }} bản ghi
        </p>

        <ul class="pagination pagination-sm mb-0">
            <li @class(['page-item', 'disabled' => $paginator->onFirstPage()])>
                <a class="page-link" href="{{ $paginator->previousPageUrl() ?? '#' }}" rel="prev" @if ($paginator->onFirstPage()) tabindex="-1" aria-disabled="true" @endif>Trước</a>
            </li>
            <li class="page-item disabled">
                <span class="page-link">Trang {{ $paginator->currentPage() }}/{{ $paginator->lastPage() }}</span>
            </li>
            <li @class(['page-item', 'disabled' => ! $paginator->hasMorePages()])>
                <a class="page-link" href="{{ $paginator->nextPageUrl() ?? '#' }}" rel="next" @unless ($paginator->hasMorePages()) tabindex="-1" aria-disabled="true" @endunless>Sau</a>
            </li>
        </ul>
    </nav>
@endif
