// Bundle của khu quản trị: Bootstrap cho hành vi component, Alpine cho các
// mảnh trạng thái nhỏ trong biểu mẫu. Trang giới thiệu công khai dùng app.js
// riêng nên không phải tải Bootstrap.
import 'bootstrap/js/dist/collapse';
import 'bootstrap/js/dist/dropdown';
import Modal from 'bootstrap/js/dist/modal';
import 'bootstrap/js/dist/offcanvas';
import Alpine from 'alpinejs';

/**
 * Editor cho các dòng dịch vụ của hóa đơn nháp.
 * Mọi con số hiển thị ở đây chỉ để xem trước; máy chủ luôn tính lại khi lưu.
 */
Alpine.data('invoiceEditor', (initialRows = []) => ({
    rows: initialRows.map((row, index) => ({ ...row, key: `existing-${index}` })),
    nextKey: 0,

    addRow() {
        this.rows.push({
            key: `new-${this.nextKey++}`,
            id: '',
            service_id: '',
            employee_id: '',
            name: '',
            quantity: '1',
            unit_price: '0',
            commission_rate: '0',
            work_context: 'regular',
            commission_rate_reason: '',
            unit_label: '',
            range_label: '',
            price_min: null,
            price_max: null,
        });
    },

    applyService(row) {
        const option = this.$el.querySelector(`option[value="${row.service_id}"][data-price]`);

        if (!option) {
            row.unit_label = '';
            row.range_label = '';
            row.price_min = null;
            row.price_max = null;

            return;
        }

        row.name = option.dataset.label;
        row.unit_price = option.dataset.price;
        row.unit_label = option.dataset.unitLabel ?? '';
        row.range_label = option.dataset.rangeLabel ?? '';
        row.price_min = option.dataset.min ? Number(option.dataset.min) : null;
        row.price_max = option.dataset.max ? Number(option.dataset.max) : null;
    },

    /**
     * Đơn giá đang nằm ngoài khoảng của bảng giá.
     * Chỉ để cảnh báo gõ nhầm; máy chủ không chặn và vẫn lưu bình thường.
     */
    priceOutOfRange(row) {
        if (row.price_min === null || row.price_max === null || row.unit_price === '') {
            return false;
        }

        const price = Number(row.unit_price);

        return Number.isFinite(price) && (price < row.price_min || price > row.price_max);
    },

    lineTotal(row) {
        return Math.round(Number(row.quantity || 0) * Number(row.unit_price || 0) * 100) / 100;
    },

    subtotal() {
        return this.rows.reduce((total, row) => total + this.lineTotal(row), 0);
    },

    formatMoney(value) {
        return new Intl.NumberFormat('vi-VN').format(value) + ' đ';
    },
}));

/** Dòng vật tư của phiếu chuyển kho. */
Alpine.data('transferEditor', (products = []) => ({
    products,
    rows: [{ key: 'row-0', product_id: '', quantity: '1', unit_cost: '0' }],
    nextKey: 1,

    addRow() {
        this.rows.push({ key: `row-${this.nextKey++}`, product_id: '', quantity: '1', unit_cost: '0' });
    },

    applyProduct(row) {
        const product = this.products.find((item) => String(item.id) === String(row.product_id));

        if (product) {
            row.unit_cost = product.cost;
        }
    },
}));

/**
 * Chặn bấm hai lần vào nút gửi biểu mẫu.
 * Lần bấm thứ hai bị nuốt, lần đầu vẫn gửi bình thường. Máy chủ vẫn là nơi
 * quyết định: mọi thao tác tài chính đều idempotent.
 */
Alpine.data('submitGuard', () => ({
    submitting: false,

    guard(event) {
        if (this.submitting) {
            event.preventDefault();

            return;
        }

        const form = event.target.closest('form');

        if (form && !form.reportValidity()) {
            return;
        }

        this.submitting = true;
    },
}));

/**
 * Nút Vào ca / Ra ca.
 *
 * Chỉ đọc vị trí đúng một lần khi người dùng bấm: không watchPosition, không
 * theo dõi nền. Toạ độ chỉ được dùng để điền vào form rồi gửi lên máy chủ;
 * máy chủ mới là nơi tính khoảng cách và quyết định chấp nhận hay không.
 */
Alpine.data('clockButton', () => ({
    busy: false,
    error: '',
    statusText: 'Đang lấy vị trí…',

    onSubmit(event) {
        // Lần gửi thứ hai (do chính hàm này kích hoạt) đã có toạ độ nên đi thẳng.
        if (this.$refs.latitude.value !== '') {
            return;
        }

        event.preventDefault();

        if (this.busy) {
            return;
        }

        if (!('geolocation' in navigator)) {
            this.error = 'Trình duyệt này không hỗ trợ định vị. Hãy dùng trình duyệt khác hoặc nhờ quản lý chấm công giúp.';

            return;
        }

        if (!window.isSecureContext) {
            this.error = 'Trang đang không chạy qua kết nối bảo mật nên trình duyệt sẽ không cho phép định vị. Hãy báo quản lý.';

            return;
        }

        this.busy = true;
        this.error = '';

        const form = event.target;

        navigator.geolocation.getCurrentPosition(
            (position) => {
                this.$refs.latitude.value = position.coords.latitude;
                this.$refs.longitude.value = position.coords.longitude;
                this.$refs.accuracy.value = Math.ceil(position.coords.accuracy);
                this.statusText = 'Đang gửi…';
                form.requestSubmit();
            },
            (failure) => {
                this.busy = false;
                this.error = this.describe(failure);
            },
            { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 },
        );
    },

    describe(failure) {
        switch (failure.code) {
            case failure.PERMISSION_DENIED:
                return 'Bạn đã từ chối quyền truy cập vị trí. Hãy mở cài đặt trình duyệt, cho phép định vị với trang này rồi thử lại.';
            case failure.POSITION_UNAVAILABLE:
                return 'Không lấy được vị trí. Hãy kiểm tra GPS đã bật chưa rồi thử lại.';
            case failure.TIMEOUT:
                return 'Lấy vị trí quá lâu. Hãy ra chỗ thoáng, tránh trong nhà sâu, rồi thử lại.';
            default:
                return 'Không lấy được vị trí. Hãy thử lại sau ít phút.';
        }
    },
}));

/**
 * Hộp xác nhận dùng chung.
 * Bắt sự kiện ở cấp document nên các dòng thêm sau vẫn hoạt động, và chỉ tồn
 * tại một modal duy nhất trong DOM dù bảng có bao nhiêu dòng.
 */
document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-neo-confirm]');

    if (!trigger) {
        return;
    }

    const form = trigger.closest('form');
    const modalEl = document.getElementById('neoConfirm');

    if (!form || !modalEl) {
        return;
    }

    event.preventDefault();

    modalEl.querySelector('[data-neo-confirm-message]').textContent = trigger.dataset.neoConfirm;

    const modal = Modal.getOrCreateInstance(modalEl);
    const accept = modalEl.querySelector('[data-neo-confirm-accept]');

    const onAccept = () => {
        accept.disabled = true;
        modal.hide();
        form.requestSubmit();
    };

    accept.addEventListener('click', onAccept, { once: true });

    modalEl.addEventListener('hidden.bs.modal', () => {
        accept.removeEventListener('click', onAccept);
        accept.disabled = false;
    }, { once: true });

    modal.show();
});

window.Alpine = Alpine;
Alpine.start();
