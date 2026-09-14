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
        });
    },

    applyService(row) {
        const option = this.$el.querySelector(`option[value="${row.service_id}"][data-price]`);

        if (option) {
            row.name = option.dataset.label;
            row.unit_price = option.dataset.price;
        }
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

window.Alpine = Alpine;

Alpine.start();

document.documentElement.classList.add('js');

const header = document.querySelector('[data-header]');
const menuToggle = document.querySelector('[data-menu-toggle]');
const menu = document.querySelector('[data-menu]');

const closeMenu = () => {
    if (!menuToggle || !menu) {
        return;
    }

    menuToggle.setAttribute('aria-expanded', 'false');
    menuToggle.setAttribute('aria-label', 'Mở menu');
    menu.classList.remove('is-open');
};

menuToggle?.addEventListener('click', () => {
    if (!menu) {
        return;
    }

    const isOpen = menuToggle.getAttribute('aria-expanded') === 'true';
    menuToggle.setAttribute('aria-expanded', String(!isOpen));
    menuToggle.setAttribute('aria-label', isOpen ? 'Mở menu' : 'Đóng menu');
    menu.classList.toggle('is-open', !isOpen);
});

menu?.querySelectorAll('a').forEach((link) => link.addEventListener('click', closeMenu));

window.addEventListener('scroll', () => {
    header?.classList.toggle('is-scrolled', window.scrollY > 8);
}, { passive: true });

const revealItems = document.querySelectorAll('[data-reveal]');

if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.12 });

    revealItems.forEach((item) => observer.observe(item));
} else {
    revealItems.forEach((item) => item.classList.add('is-visible'));
}

const bookingSuccessDialog = document.querySelector('[data-booking-success-dialog]');
const bookingSuccessClose = document.querySelector('[data-booking-success-close]');

if (bookingSuccessDialog instanceof HTMLDialogElement) {
    bookingSuccessDialog.showModal();

    bookingSuccessClose?.addEventListener('click', () => bookingSuccessDialog.close());

    bookingSuccessDialog.addEventListener('click', (event) => {
        if (event.target === bookingSuccessDialog) {
            bookingSuccessDialog.close();
        }
    });
}
