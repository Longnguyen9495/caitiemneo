{{--
    Kết thúc hoặc xóa một ca cố định.

    Ca đã chạy qua ngày thật thì chỉ đóng lại bằng một ngày kết thúc, vì chính
    nó giải thích tại sao lịch những tháng trước trông như vậy. Ca chưa tới ngày
    hiệu lực thì chưa sinh ra gì cả nên xóa hẳn được.
--}}
@can('update', $fixedShift)
    <div class="d-flex flex-wrap align-items-end gap-2">
        @if ($fixedShift->isOpenEnded())
            <form method="POST" action="{{ route('admin.employee-shift-plans.fixed-shifts.end', $fixedShift) }}" class="d-flex align-items-end gap-1">
                @csrf
                @method('PATCH')
                <div>
                    <label class="form-label small mb-1" for="effective_to_{{ $fixedShift->id }}">Kết thúc từ</label>
                    <input class="form-control form-control-sm neo-num" type="date"
                           id="effective_to_{{ $fixedShift->id }}" name="effective_to"
                           value="{{ old('effective_to', now()->toDateString()) }}"
                           min="{{ $fixedShift->effective_from->toDateString() }}" required>
                </div>
                <button class="btn btn-sm btn-outline-secondary" type="submit">Kết thúc</button>
            </form>
        @endif

        @unless ($fixedShift->hasTakenEffect())
            <x-admin.confirm-form
                :action="route('admin.employee-shift-plans.fixed-shifts.destroy', $fixedShift)"
                method="DELETE"
                label="Xóa"
                :message="'Xóa ca cố định của '.$fixedShift->employee?->name.' (chưa tới ngày hiệu lực)?'"
            />
        @endunless
    </div>
@endcan
