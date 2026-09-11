@php
    $branchContext = app(App\Support\BranchContext::class);
    $branches = $branchContext->available();
@endphp

@if ($branches->isNotEmpty())
    <form method="POST" action="{{ route('admin.branch.switch') }}" class="admin-branch-switcher">
        @csrf
        <label for="branch-switcher">Chi nhánh</label>
        <select id="branch-switcher" name="branch" onchange="this.form.submit()" @disabled($branches->count() === 1 && ! $branchContext->mayViewAll())>
            @if ($branchContext->mayViewAll())
                <option value="all" @selected($branchContext->viewingAll())>Tất cả chi nhánh</option>
            @endif
            @foreach ($branches as $branch)
                <option value="{{ $branch->id }}" @selected(! $branchContext->viewingAll() && $branchContext->currentId() === $branch->id)>
                    {{ $branch->name }}
                </option>
            @endforeach
        </select>
        <noscript><button type="submit">Chuyển</button></noscript>
    </form>
@endif
