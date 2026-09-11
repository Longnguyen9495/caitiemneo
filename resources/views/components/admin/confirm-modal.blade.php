{{--
    Một hộp xác nhận dùng chung cho toàn trang.
    Mỗi nút nguy hiểm chỉ cần gắn data-neo-confirm="câu hỏi"; hộp thoại sẽ gửi
    đúng form chứa nút đó. Cách này thay cho window.confirm của trình duyệt và
    tránh việc phải dựng một modal cho mỗi dòng trong bảng.
--}}
<div class="modal fade" id="neoConfirm" tabindex="-1" aria-labelledby="neoConfirmLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h2 class="modal-title fs-6 fw-semibold" id="neoConfirmLabel">Xác nhận thao tác</h2>
            </div>
            <div class="modal-body" data-neo-confirm-message>Bạn có chắc chắn muốn thực hiện thao tác này?</div>
            <div class="modal-footer border-0 pt-0 gap-2 flex-nowrap">
                <button type="button" class="btn btn-light flex-fill" data-bs-dismiss="modal">Quay lại</button>
                <button type="button" class="btn btn-danger flex-fill" data-neo-confirm-accept>Đồng ý</button>
            </div>
        </div>
    </div>
</div>
