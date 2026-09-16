// Bundle của khu quản trị: Bootstrap cho hành vi component, Alpine cho các
// mảnh trạng thái nhỏ trong biểu mẫu. Trang giới thiệu công khai dùng app.js
// riêng nên không phải tải Bootstrap.
import 'bootstrap/js/dist/collapse';
import 'bootstrap/js/dist/dropdown';
import Modal from 'bootstrap/js/dist/modal';
import 'bootstrap/js/dist/offcanvas';
import Alpine from 'alpinejs';
import { searchableSelect } from './searchable-select';

/**
 * Ô tiền tệ dùng dấu chấm phân tách hàng nghìn khi không nhập liệu. Giá trị
 * được bỏ định dạng ngay trước khi gửi, để các Form Request tiếp tục nhận số
 * thập phân chuẩn và không phụ thuộc vào cách trình duyệt xử lý locale.
 */
const moneyInputValue = (value) => {
    const normalized = String(value ?? '')
        .trim()
        .replace(/\s/g, '')
        .replace(/\./g, '')
        .replace(',', '.');

    return normalized === '' || Number.isNaN(Number(normalized)) ? '' : normalized;
};

const formatMoneyInput = (input) => {
    const value = moneyInputValue(input.value);

    if (value === '') {
        input.value = '';

        return;
    }

    const [whole, decimal] = value.split('.');
    input.value = new Intl.NumberFormat('vi-VN').format(Number(whole))
        + (decimal === undefined ? '' : `,${decimal}`);
};

document.addEventListener('focusin', (event) => {
    if (event.target.matches('[data-money-input]')) {
        event.target.value = moneyInputValue(event.target.value);
    }
});

document.addEventListener('focusout', (event) => {
    if (event.target.matches('[data-money-input]')) {
        formatMoneyInput(event.target);
    }
});

document.addEventListener('submit', (event) => {
    event.target.querySelectorAll('[data-money-input]').forEach((input) => {
        input.value = moneyInputValue(input.value);
    });
});

document.querySelectorAll('[data-money-input]').forEach(formatMoneyInput);

/**
 * Editor cho các dòng dịch vụ của hóa đơn nháp.
 * Mọi con số hiển thị ở đây chỉ để xem trước; máy chủ luôn tính lại khi lưu.
 */
Alpine.data('invoiceEditor', (initialRows = [], serverErrors = {}) => ({
    rows: initialRows.map((row, index) => ({
        ...row,
        quantity: String(row.quantity ?? '').replace(/(\.\d*?[1-9])0+$|\.0+$/, '$1'),
        unit_price: new Intl.NumberFormat('vi-VN').format(Number(row.unit_price || 0)),
        key: `existing-${index}`,
    })),
    nextKey: 0,

    /*
     * Lỗi do server trả về, gắn đúng dòng đã gây ra lỗi.
     * Alpine dựng các dòng bằng x-for nên Blade không biết chỉ số dòng lúc
     * render; đưa nguyên mảng lỗi sang đây rồi tra theo `items.N.field`.
     */
    serverErrors,

    rowError(index, field) {
        const messages = this.serverErrors[`items.${index}.${field}`];

        return messages && messages.length ? messages[0] : '';
    },

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
            price_override_reason: '',
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
        row.unit_price = this.normalizeMoney(option.dataset.price);
        row.unit_label = option.dataset.unitLabel ?? '';
        row.range_label = option.dataset.rangeLabel ?? '';
        row.price_min = option.dataset.min ? Number(option.dataset.min) : null;
        row.price_max = option.dataset.max ? Number(option.dataset.max) : null;
    },

    /**
     * Đơn giá đang nằm ngoài khoảng của bảng giá.
     *
     * Khoảng giá vẫn là khoảng tham chiếu chứ không phải rào cứng, nhưng ra
     * ngoài khoảng là quyết định của quản lý và phải kèm lý do: máy chủ mới là
     * nơi kiểm tra điều đó, phần này chỉ hiện ô lý do cho đúng lúc.
     */
    priceOutOfRange(row) {
        if (row.price_min === null || row.price_max === null || row.unit_price === '') {
            return false;
        }

        const price = Number(this.normalizeMoney(row.unit_price));

        return Number.isFinite(price) && (price < row.price_min || price > row.price_max);
    },

    /**
     * Số lượng không cần hiển thị các số 0 vô nghĩa (1.00 thành 1), nhưng vẫn
     * chấp nhận phần lẻ khi đơn vị dịch vụ yêu cầu. Lưu chuỗi để không làm hỏng
     * giá trị người dùng đang gõ như `1,5`.
     */
    normalizeQuantity(value) {
        const normalized = String(value ?? '')
            .trim()
            .replace(/\s/g, '')
            .replace(',', '.');

        if (normalized === '' || !/^\d*(?:\.\d*)?$/.test(normalized)) {
            return '';
        }

        const [whole = '0', decimal] = normalized.split('.');
        const trimmedDecimal = decimal?.replace(/0+$/, '');

        return trimmedDecimal ? `${Number(whole)}.${trimmedDecimal}` : String(Number(whole));
    },

    /** Giá VND luôn là số nguyên; chấm được dùng làm dấu ngăn cách hàng nghìn. */
    normalizeMoney(value) {
        const digits = String(value ?? '').replace(/\D/g, '');

        return digits === '' ? '' : String(Number(digits));
    },

    formatMoneyInput(value) {
        const normalized = this.normalizeMoney(value);

        return normalized === '' ? '' : new Intl.NumberFormat('vi-VN').format(Number(normalized));
    },

    lineTotal(row) {
        return Math.round(Number(this.normalizeQuantity(row.quantity) || 0) * Number(this.normalizeMoney(row.unit_price) || 0) * 100) / 100;
    },

    commissionAmount(row) {
        return Math.round(this.lineTotal(row) * Number(row.commission_rate || 0)) / 100;
    },

    prepareForSubmit() {
        this.rows.forEach((row) => {
            row.quantity = this.normalizeQuantity(row.quantity);
            row.unit_price = this.normalizeMoney(row.unit_price);
        });
    },

    formatPercent(value) {
        return new Intl.NumberFormat('vi-VN', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 2,
        }).format(Number(value || 0)) + '%';
    },

    subtotal() {
        return this.rows.reduce((total, row) => total + this.lineTotal(row), 0);
    },

    formatMoney(value) {
        return new Intl.NumberFormat('vi-VN').format(value) + ' đ';
    },
}));

/** Dòng vật tư của phiếu chuyển kho. */
Alpine.data('transferEditor', (products = [], initialRows = [], serverErrors = {}) => ({
    products,

    /*
     * Giữ lại đúng các dòng người dùng đã gõ khi form bị trả về vì lỗi.
     * Dựng lại một dòng trống đồng nghĩa bắt họ gõ lại từ đầu, và đó là lúc
     * người ta bỏ form rồi chuyển kho tay không giấy tờ.
     */
    rows: initialRows.length
        ? initialRows.map((row, index) => ({ ...row, key: `old-${index}` }))
        : [{ key: 'row-0', product_id: '', quantity: '1', unit_cost: '0' }],
    nextKey: initialRows.length || 1,

    serverErrors,

    /** Lỗi của đúng dòng này, tra theo khóa `items.N.field`. */
    rowError(index, field) {
        const messages = this.serverErrors[`items.${index}.${field}`];

        return messages && messages.length ? messages[0] : '';
    },

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

    /*
     * Thao tác phá hủy có thể yêu cầu lý do do người dùng gõ. Nút gọi tới khai
     * báo data-neo-confirm-reason="tên input ẩn trong form"; giá trị chỉ được
     * chép sang form khi hợp lệ, và server vẫn là nơi kiểm tra cuối cùng.
     */
    const reasonField = trigger.dataset.neoConfirmReason || null;
    const wrap = modalEl.querySelector('[data-neo-confirm-reason-wrap]');
    const textarea = modalEl.querySelector('[data-neo-confirm-reason]');
    const error = modalEl.querySelector('[data-neo-confirm-reason-error]');
    const label = modalEl.querySelector('[data-neo-confirm-reason-label]');
    const minLength = Number(trigger.dataset.neoConfirmReasonMin || 10);

    wrap.hidden = !reasonField;
    textarea.value = '';
    textarea.classList.remove('is-invalid');
    textarea.setAttribute('aria-invalid', 'false');
    textarea.minLength = minLength;
    error.classList.add('d-none');

    if (reasonField) {
        label.textContent = trigger.dataset.neoConfirmReasonLabel || 'Lý do';
    }

    const onAccept = () => {
        if (reasonField) {
            const value = textarea.value.trim();

            if (value.length < minLength) {
                textarea.classList.add('is-invalid');
                textarea.setAttribute('aria-invalid', 'true');
                error.classList.remove('d-none');
                textarea.focus();

                return;
            }

            let hidden = form.querySelector(`input[name="${reasonField}"]`);

            if (!hidden) {
                hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = reasonField;
                form.appendChild(hidden);
            }

            hidden.value = value;
        }

        accept.disabled = true;
        modal.hide();
        form.requestSubmit();
    };

    accept.addEventListener('click', onAccept);

    modalEl.addEventListener('hidden.bs.modal', () => {
        accept.removeEventListener('click', onAccept);
        accept.disabled = false;
    }, { once: true });

    modalEl.addEventListener('shown.bs.modal', () => {
        if (reasonField) {
            textarea.focus();
        }
    }, { once: true });

    modal.show();
});

/*
 * Cảnh báo trước khi rời trang mà chưa lưu.
 *
 * Gắn vào biểu mẫu bằng thuộc tính `data-neo-dirty-guard`. Chỉ cảnh báo khi
 * người dùng đã thực sự gõ gì đó, và không cảnh báo khi chính họ bấm gửi —
 * nếu không, hộp thoại sẽ bật ra ở mọi lần lưu và người ta sẽ học cách bấm
 * qua nó mà không đọc.
 */
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-neo-dirty-guard]').forEach((form) => {
        let dirty = false;
        let submitting = false;

        form.addEventListener('input', () => {
            dirty = true;
        });

        form.addEventListener('submit', () => {
            submitting = true;
        });

        window.addEventListener('beforeunload', (event) => {
            if (!dirty || submitting) {
                return;
            }

            // Trình duyệt hiện câu chữ của riêng nó; chỉ cần preventDefault.
            event.preventDefault();
            event.returnValue = '';
        });
    });
});

/*
 * Nối lỗi biểu mẫu với đúng control, và đưa tiêu điểm tới chỗ cần sửa.
 *
 * Phần nhãn, câu lỗi và câu trợ giúp do Blade dựng (x-admin.field), nhưng bản
 * thân ô nhập nằm trong slot nên Blade không gắn thuộc tính vào nó được. Ở đây
 * đọc các dấu mốc `data-neo-*` mà component để lại rồi gắn `aria-invalid` cùng
 * `aria-describedby` vào đúng control.
 *
 * Đây chỉ là phần hỗ trợ: câu lỗi đã hiển thị sẵn trong HTML kể cả khi không
 * có JavaScript, và máy chủ vẫn là nơi quyết định.
 */
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-neo-describedby]').forEach((wrapper) => {
        const control = wrapper.querySelector('input, select, textarea');

        if (!control) {
            return;
        }

        const existing = control.getAttribute('aria-describedby');
        const ids = wrapper.dataset.neoDescribedby;

        control.setAttribute('aria-describedby', existing ? `${existing} ${ids}` : ids);

        if (wrapper.hasAttribute('data-neo-invalid')) {
            control.setAttribute('aria-invalid', 'true');
            control.classList.add('is-invalid');
        }
    });

    // Đưa người dùng thẳng tới chỗ cần sửa thay vì để họ tự dò từ đầu trang.
    const summary = document.querySelector('[data-neo-error-summary]');

    if (!summary) {
        return;
    }

    const firstInvalid = document.querySelector('[data-neo-invalid]');
    const target = firstInvalid?.querySelector('input, select, textarea') ?? summary;

    target.focus({ preventScroll: true });
    target.scrollIntoView({ block: 'center', behavior: 'smooth' });
});

/*
 * Thông báo kết quả hiện thành hộp thoại.
 *
 * Blade render thông báo thẳng vào trang để nó vẫn đọc được khi JavaScript
 * hỏng hoặc chưa tải xong. Ở đây ta nhấc nội dung đó vào hộp thoại dùng chung
 * rồi mở lên, nên mọi màn hình trong khu quản trị báo theo đúng một cách mà
 * không màn nào phải tự khai báo gì.
 */
document.addEventListener('DOMContentLoaded', () => {
    const source = document.querySelector('[data-neo-notice]');
    const modalEl = document.getElementById('neoNotice');

    if (!source || !modalEl) {
        return;
    }

    const isError = source.dataset.neoNoticeTone === 'danger';
    const dismiss = modalEl.querySelector('[data-neo-notice-dismiss]');

    modalEl.querySelector('[data-neo-notice-title]').textContent = isError ? 'Chưa thực hiện được' : 'Đã xong';
    modalEl.querySelector('[data-neo-notice-text]').textContent = source.querySelector('[data-neo-notice-text]').textContent.trim();
    modalEl.querySelector('[data-neo-notice-icon-check]').classList.toggle('d-none', isError);
    modalEl.querySelector('[data-neo-notice-icon-alert]').classList.toggle('d-none', !isError);
    modalEl.querySelector('[data-neo-notice-mark]').classList.toggle('neo-notice__mark--danger', isError);
    dismiss.classList.toggle('btn-danger', isError);
    dismiss.classList.toggle('btn-primary', !isError);

    // Gỡ bản nằm trong trang đi. Không gỡ thì thông báo hiện hai lần: một
    // trong hộp thoại, một nằm lại phía sau lớp phủ khi người dùng đóng.
    source.remove();

    Modal.getOrCreateInstance(modalEl).show();
});

window.Alpine = Alpine;

Alpine.data('searchableSelect', searchableSelect);

/**
 * Tối thiểu: mở/đóng panel chi tiết ngày trên lịch.
 * Nội dung panel được server render; Alpine chỉ điều khiển hiển thị.
 */
Alpine.data('calendarPanel', () => ({
    activeDate: null,
    activeDateLabel: '',

    open(date) {
        this.activeDate = date;
        const btn = document.querySelector(`[data-cal-date="${date}"]`);
        this.activeDateLabel = btn ? btn.getAttribute('aria-label') : date;
    },

    close() {
        this.activeDate = null;
        this.activeDateLabel = '';
    },
}));

Alpine.start();
