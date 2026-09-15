import Alpine from 'alpinejs';
import { searchableSelect } from './searchable-select';

// Editor biểu mẫu (invoiceEditor, transferEditor, submitGuard, clockButton)
// chỉ sống trong bundle quản trị tại resources/js/admin.js. Bản sao ở đây là
// di sản và không trang công khai nào dùng tới, vì mọi màn hình có các editor
// đó đều tải admin.js.
//
// `searchableSelect` là ngoại lệ: màn hình hồ sơ nằm ngoài khu quản trị nhưng
// vẫn cần ô chọn ngân hàng, nên nó được đăng ký ở cả hai bundle.

window.Alpine = Alpine;

Alpine.data('searchableSelect', searchableSelect);

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

const bookingForm = document.querySelector('[data-booking-form]');
const bookingSubmitStatus = document.querySelector('[data-booking-submit-status]');

bookingForm?.addEventListener('submit', () => {
    const submitButton = bookingForm.querySelector('button[type="submit"]');

    if (bookingSubmitStatus) {
        bookingSubmitStatus.hidden = false;
        bookingSubmitStatus.textContent = 'Đang gửi yêu cầu đặt lịch…';
    }

    if (submitButton) {
        submitButton.disabled = true;
        submitButton.setAttribute('aria-disabled', 'true');
    }
});

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
