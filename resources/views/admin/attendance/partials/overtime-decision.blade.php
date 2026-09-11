{{--
    Quyết định tăng ca cho một ca.

    Số phút duyệt mặc định đúng bằng số phút hệ thống ghi nhận; quản lý có thể
    giảm xuống nhưng không tăng lên — máy chủ chặn lại nếu vượt.
--}}
<form method="POST" action="{{ route('admin.attendance.overtime', $record) }}" class="row g-1 align-items-end">
    @csrf
    @method('PATCH')

    <div class="col-5 col-lg-4">
        <label class="visually-hidden" for="minutes-{{ $record->id }}">Số phút duyệt</label>
        <input class="form-control form-control-sm neo-num" id="minutes-{{ $record->id }}"
               type="number" name="approved_overtime_minutes" min="0" max="{{ $record->overtime_minutes }}"
               value="{{ $record->overtime_minutes }}" aria-describedby="minutes-help-{{ $record->id }}">
        <span class="visually-hidden" id="minutes-help-{{ $record->id }}">Tối đa {{ $record->overtime_minutes }} phút</span>
    </div>

    <div class="col-12 col-lg-8 d-flex gap-1">
        <button type="submit" name="overtime_status" value="approved" class="btn btn-sm btn-primary flex-fill">Duyệt</button>
        <button type="submit" name="overtime_status" value="rejected" class="btn btn-sm btn-outline-danger flex-fill">Từ chối</button>
    </div>

    <div class="col-12">
        <label class="visually-hidden" for="note-{{ $record->id }}">Ghi chú</label>
        <input class="form-control form-control-sm mt-1" id="note-{{ $record->id }}"
               name="overtime_approval_note" maxlength="255" placeholder="Ghi chú (không bắt buộc)">
    </div>
</form>
