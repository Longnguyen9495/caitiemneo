{{--
    Chọn người thay cho một ca đã được duyệt nghỉ.

    Danh sách ứng viên do `AssignShiftReplacementAction::candidates()` dựng: đã
    loại người trùng giờ, người không thuộc chi nhánh hôm đó và người đang có
    đơn treo. Rỗng nghĩa là hôm ấy thật sự không còn ai rảnh, nên nói thẳng
    thay vì đưa ra một ô chọn không có gì để chọn.
--}}
@if ($candidates->isNotEmpty())
    <form method="POST" action="{{ route('admin.shift-requests.replacement.assign', $shiftRequest) }}" class="d-flex gap-1 align-items-center">
        @csrf
        <label class="visually-hidden" for="replacement_{{ $shiftRequest->id }}">Nhân viên thay ca</label>
        <select class="form-select form-select-sm" id="replacement_{{ $shiftRequest->id }}" name="replacement_employee_id" required>
            <option value="">Chọn người thay</option>
            @foreach ($candidates as $candidate)
                <option value="{{ $candidate->id }}">{{ $candidate->name }}</option>
            @endforeach
        </select>
        <button class="btn btn-sm btn-success" type="submit">Phân thay</button>
    </form>
@else
    <span class="small text-body-secondary">Chưa có ứng viên phù hợp.</span>
@endif
