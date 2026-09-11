@php
    $branchContext = app(App\Support\BranchContext::class);
    $branches = $branchContext->available();
    $onlyOne = $branches->count() === 1 && ! $branchContext->mayViewAll();
    $currentLabel = $branchContext->viewingAll()
        ? 'Tất cả chi nhánh'
        : ($branchContext->current()?->name ?? 'Chi nhánh');
@endphp

@if ($branches->isNotEmpty())
    @if ($onlyOne)
        {{-- Một chi nhánh thì không có gì để chọn: chỉ hiển thị tên. --}}
        <span class="badge text-bg-light border fw-semibold text-truncate" style="max-width:11rem">{{ $currentLabel }}</span>
    @else
        <div class="dropdown">
            <button class="btn btn-sm btn-outline-secondary dropdown-toggle text-truncate" type="button"
                    data-bs-toggle="dropdown" data-bs-auto-close="true" aria-expanded="false"
                    style="max-width:11rem" aria-label="Đổi chi nhánh làm việc, đang chọn {{ $currentLabel }}">
                {{ $currentLabel }}
            </button>

            <ul class="dropdown-menu dropdown-menu-end shadow">
                <li><h3 class="dropdown-header">Chi nhánh làm việc</h3></li>

                @if ($branchContext->mayViewAll())
                    <li>
                        <form method="POST" action="{{ route('admin.branch.switch') }}">
                            @csrf
                            <input type="hidden" name="branch" value="all">
                            <button type="submit" @class(['dropdown-item', 'active' => $branchContext->viewingAll()])>
                                Tất cả chi nhánh
                            </button>
                        </form>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                @endif

                @foreach ($branches as $branch)
                    <li>
                        <form method="POST" action="{{ route('admin.branch.switch') }}">
                            @csrf
                            <input type="hidden" name="branch" value="{{ $branch->id }}">
                            <button type="submit" @class([
                                'dropdown-item',
                                'active' => ! $branchContext->viewingAll() && $branchContext->currentId() === $branch->id,
                            ])>
                                {{ $branch->name }}
                                <small class="d-block opacity-75">{{ $branch->code }}</small>
                            </button>
                        </form>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
@endif
