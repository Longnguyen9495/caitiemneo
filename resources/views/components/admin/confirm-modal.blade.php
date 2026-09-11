{{--
    Một hộp xác nhận dùng chung cho toàn trang.
    Mỗi nút nguy hiểm chỉ cần gắn data-neo-confirm="câu hỏi"; hộp thoại sẽ gửi
    đúng form chứa nút đó. Cách này thay cho window.confirm của trình duyệt và
    tránh việc phải dựng một modal cho mỗi dòng trong bảng.
--}}
<div class="modal fade" id="neoConfirm" tabindex="-1" aria-labelledby="neoConfirmLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h2 class="modal-title fs-6 fw-semibold" id="neoConfirmLabel">Xác nhận thao tác</h2>
            </div>
            <div class="modal-body">
                <p class="mb-0" data-neo-confirm-message>Bạn có chắc chắn muốn thực hiện thao tác này?</p>

                {{--
                    Ô lý do chỉ hiện khi nút gọi tới yêu cầu, và giá trị được
                    chép sang input ẩn của chính form đó khi bấm Đồng ý.
                    Lý do luôn do người dùng gõ, không có sẵn giá trị mặc định.
                --}}
                <div class="mt-3" data-neo-confirm-reason-wrap hidden>
                    <label for="neoConfirmReason" class="form-label small fw-semibold" data-neo-confirm-reason-label>Lý do</label>
                    <textarea id="neoConfirmReason" class="form-control" rows="2" data-neo-confirm-reason
                              minlength="10" maxlength="255" required></textarea>
                    <p class="form-text mb-0" data-neo-confirm-reason-help>Ghi rõ để người đối soát sau hiểu được.</p>
                    <p class="invalid-feedback d-none" data-neo-confirm-reason-error role="alert">Hãy ghi lý do cụ thể, ít nhất 10 ký tự.</p>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0 gap-2 flex-nowrap">
                <button type="button" class="btn btn-light flex-fill" data-bs-dismiss="modal">Quay lại</button>
                <button type="button" class="btn btn-danger flex-fill" data-neo-confirm-accept>Đồng ý</button>
            </div>
        </div>
    </div>
</div>
