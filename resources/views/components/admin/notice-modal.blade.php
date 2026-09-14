{{--
    Hộp thoại thông báo dùng chung cho toàn khu quản trị.

    Chỉ có đúng một hộp này trong layout. `admin.js` chuyển nội dung của
    `[data-neo-notice]` vào đây rồi mở, nên thêm một màn hình mới không phải
    khai báo gì: cứ `session('success')` hoặc `session('error')` là có thông
    báo đúng kiểu.

    Tiêu đề và màu do JavaScript đặt theo tông của thông báo.
--}}
<div class="modal fade" id="neoNotice" tabindex="-1" aria-labelledby="neoNoticeLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center pt-4">
                <span class="neo-notice__mark d-inline-flex align-items-center justify-content-center rounded-circle mb-3"
                      data-neo-notice-mark>
                    {{-- Dùng d-none chứ không dùng thuộc tính hidden: hidden là
                         thuộc tính của HTML, áp lên phần tử SVG không chắc ăn. --}}
                    <x-admin.icon name="check" size="26" data-neo-notice-icon-check />
                    <x-admin.icon name="alert" size="26" data-neo-notice-icon-alert class="d-none" />
                </span>

                <h2 class="modal-title fs-6 fw-semibold mb-1" id="neoNoticeLabel" data-neo-notice-title>Đã xong</h2>
                <p class="mb-0 text-body-secondary" data-neo-notice-text></p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-primary w-100" data-bs-dismiss="modal" data-neo-notice-dismiss>Đã hiểu</button>
            </div>
        </div>
    </div>
</div>
