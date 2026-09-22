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

menu?.querySelectorAll('a, button').forEach((item) => item.addEventListener('click', closeMenu));

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

// Dải ảnh mẫu móng. Trên điện thoại khách vuốt ngang, trên máy tính hai nút
// mũi tên đẩy dải đi đúng hai thẻ một lần.
const galleryRail = document.querySelector('[data-gallery-rail]');

const scrollGallery = (direction) => {
    if (!galleryRail) {
        return;
    }

    const card = galleryRail.querySelector('li');
    const step = card ? card.getBoundingClientRect().width + 16 : galleryRail.clientWidth * 0.8;

    galleryRail.scrollBy({ left: step * direction * 2, behavior: 'smooth' });
};

document.querySelector('[data-gallery-prev]')?.addEventListener('click', () => scrollGallery(-1));
document.querySelector('[data-gallery-next]')?.addEventListener('click', () => scrollGallery(1));

// Xem ảnh phóng to. Ảnh trong dải đã mang sẵn srcset đủ khổ nên hộp phóng to
// chỉ mượn lại, không phải tải thêm đường dẫn nào khác.
const lightbox = document.querySelector('[data-lightbox]');
const lightboxImage = document.querySelector('[data-lightbox-image]');
const lightboxCounter = document.querySelector('[data-lightbox-counter]');
const lightboxGroups = Array.from(document.querySelectorAll('[data-lightbox-group]'))
    .map((group) => Array.from(group.querySelectorAll('[data-lightbox-open]')))
    .filter((triggers) => triggers.length > 0);

if (lightbox instanceof HTMLDialogElement && lightboxImage && lightboxGroups.length > 0) {
    // Mỗi dải ảnh là một chuỗi riêng. Mở từ album mẫu móng thì hai mũi tên chỉ
    // đi trong album đó, không lạc sang ảnh feedback của khách và ngược lại.
    let sequence = lightboxGroups[0];
    let currentPhoto = 0;

    const showPhoto = (index) => {
        if (sequence.length === 0) {
            return;
        }

        currentPhoto = (index + sequence.length) % sequence.length;

        const thumbnail = sequence[currentPhoto].querySelector('img');

        if (!thumbnail) {
            return;
        }

        lightboxImage.srcset = thumbnail.srcset;
        lightboxImage.sizes = '(max-width: 760px) 92vw, 44rem';
        lightboxImage.src = thumbnail.src;
        // Lấy từ thuộc tính chứ không phải `.width`: thuộc tính giữ kích thước thật
        // của ảnh, còn `.width` trả về bề ngang thẻ ảnh đang hiển thị trong dải.
        lightboxImage.setAttribute('width', thumbnail.getAttribute('width') ?? '');
        lightboxImage.setAttribute('height', thumbnail.getAttribute('height') ?? '');
        lightboxImage.alt = thumbnail.alt;

        if (lightboxCounter) {
            lightboxCounter.textContent = `${currentPhoto + 1} / ${sequence.length}`;
        }
    };

    lightboxGroups.forEach((triggers) => {
        triggers.forEach((trigger, index) => {
            trigger.addEventListener('click', () => {
                sequence = triggers;
                showPhoto(index);
                lightbox.showModal();
            });
        });
    });

    document.querySelector('[data-lightbox-prev]')?.addEventListener('click', () => showPhoto(currentPhoto - 1));
    document.querySelector('[data-lightbox-next]')?.addEventListener('click', () => showPhoto(currentPhoto + 1));
    document.querySelector('[data-lightbox-close]')?.addEventListener('click', () => lightbox.close());

    lightbox.addEventListener('click', (event) => {
        if (event.target === lightbox) {
            lightbox.close();
        }
    });

    lightbox.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowLeft') {
            showPhoto(currentPhoto - 1);
        }

        if (event.key === 'ArrowRight') {
            showPhoto(currentPhoto + 1);
        }
    });

    // Vuốt ngang để chuyển ảnh: cử chỉ quen thuộc nhất khi xem ảnh trên điện thoại.
    let touchStartX = null;

    lightbox.addEventListener('touchstart', (event) => {
        touchStartX = event.changedTouches[0].clientX;
    }, { passive: true });

    lightbox.addEventListener('touchend', (event) => {
        if (touchStartX === null) {
            return;
        }

        const distance = event.changedTouches[0].clientX - touchStartX;
        touchStartX = null;

        if (Math.abs(distance) > 45) {
            showPhoto(currentPhoto + (distance < 0 ? 1 : -1));
        }
    }, { passive: true });
}

// Ô chọn dịch vụ gộp theo nhóm: đầu mỗi nhóm hiện số dịch vụ, và khi khách đã
// chọn thì đổi thành số đã chọn để biết mình để quên gì trong nhóm đang đóng.
const servicePicker = document.querySelector('.service-picker');

if (servicePicker) {
    const pickerSummary = servicePicker.querySelector('[data-service-summary]');

    const refreshServiceCounts = () => {
        let picked = 0;

        servicePicker.querySelectorAll('.service-group').forEach((group) => {
            const count = group.querySelectorAll('input[type="checkbox"]:checked').length;
            const meta = group.querySelector('[data-service-group-meta]');

            picked += count;

            if (meta) {
                meta.textContent = count > 0 ? `${count} đã chọn` : `${meta.dataset.serviceTotal} dịch vụ`;
                meta.classList.toggle('is-picked', count > 0);
            }
        });

        if (pickerSummary) {
            pickerSummary.textContent = picked > 0 ? `Đã chọn ${picked} dịch vụ.` : 'Chưa chọn dịch vụ nào.';
            pickerSummary.classList.toggle('is-picked', picked > 0);
        }
    };

    servicePicker.addEventListener('change', refreshServiceCounts);
    refreshServiceCounts();
}

// Trang album: chạm vào một mẫu là mở thẳng ô đặt lịch, mang theo đúng tấm ảnh
// khách vừa bấm. Cả trang chỉ có một biểu mẫu — mỗi thẻ ảnh chỉ đổi phần xem
// trước và ô ẩn mang mã mẫu, nên album dài bao nhiêu trang cũng không nặng thêm.
const bookingDialog = document.querySelector('[data-booking-dialog]');

if (bookingDialog instanceof HTMLDialogElement) {
    const sheetViewport = window.matchMedia('(max-width: 760px)');

    // Khóa trang phía sau trong lúc tấm trượt mở, và chỉ ở khổ điện thoại.
    //
    // Hai thứ được giải quyết cùng lúc: vuốt hết biểu mẫu thì trang phía sau
    // không chạy theo, và thanh cuộn của trang biến mất nên tấm trượt bám được
    // đúng hai mép màn hình thay vì hụt một vạch bằng bề rộng thanh cuộn.
    //
    // Trên máy tính thì không khóa: ở đó hộp nằm giữa màn hình, còn việc thanh
    // cuộn mất đi làm cả trang phía sau nhảy ngang ngay lúc hộp mở ra.
    const holdPageStill = (held) => {
        document.documentElement.classList.toggle('has-sheet', held && sheetViewport.matches);
    };

    const photoFigure = bookingDialog.querySelector('[data-booking-photo]');
    const photoImage = bookingDialog.querySelector('[data-booking-photo-image]');
    const photoLabel = bookingDialog.querySelector('[data-booking-photo-label]');
    const photoInput = bookingDialog.querySelector('[data-booking-photo-input]');

    // Không kèm mẫu nào thì phải xóa hẳn dấu vết của lần mở trước, nếu không
    // khách bấm "Đặt lịch" trên thanh menu lại gửi đi tấm ảnh họ vừa đóng lại.
    const showPickedPhoto = (trigger) => {
        const thumbnail = trigger?.querySelector('img');

        if (photoInput) {
            photoInput.value = thumbnail ? (trigger.dataset.photoId ?? '') : '';
        }

        if (!photoFigure || !photoImage) {
            return;
        }

        if (!thumbnail) {
            photoFigure.hidden = true;
            photoImage.removeAttribute('src');
            photoImage.removeAttribute('srcset');

            if (photoLabel) {
                photoLabel.textContent = '';
            }

            return;
        }

        photoImage.srcset = thumbnail.srcset;
        photoImage.src = thumbnail.src;
        // Lấy từ thuộc tính chứ không phải `.width`: thuộc tính giữ kích thước
        // thật của ảnh, còn `.width` trả về bề ngang thẻ ảnh trong lưới.
        photoImage.setAttribute('width', thumbnail.getAttribute('width') ?? '');
        photoImage.setAttribute('height', thumbnail.getAttribute('height') ?? '');
        photoImage.alt = thumbnail.alt;
        photoFigure.hidden = false;

        if (photoLabel) {
            photoLabel.textContent = ` · số ${trigger.dataset.photoNumber ?? ''}`;
        }
    };

    const openBookingDialog = (trigger) => {
        showPickedPhoto(trigger);

        if (!bookingDialog.open) {
            bookingDialog.showModal();
        }

        holdPageStill(true);
    };

    // Bắt ở sự kiện `close` để bắt luôn mọi đường đóng: nút đóng, bấm ra ngoài,
    // phím Esc, và cả lúc biểu mẫu được gửi đi.
    bookingDialog.addEventListener('close', () => holdPageStill(false));

    document.querySelectorAll('[data-lookbook-open]').forEach((trigger) => {
        trigger.addEventListener('click', () => openBookingDialog(trigger));
    });

    document.querySelectorAll('[data-booking-open]').forEach((trigger) => {
        trigger.addEventListener('click', () => openBookingDialog(null));
    });

    bookingDialog.querySelector('[data-booking-dialog-close]')
        ?.addEventListener('click', () => bookingDialog.close());

    bookingDialog.addEventListener('click', (event) => {
        if (event.target === bookingDialog) {
            bookingDialog.close();
        }
    });

    // Biểu mẫu bị trả về vì thiếu thông tin: mở lại ngay với những gì khách đã
    // điền, thay vì để họ nhìn một trang ảnh và tự đoán chuyện gì vừa xảy ra.
    if (bookingDialog.hasAttribute('data-booking-reopen')) {
        bookingDialog.showModal();
        holdPageStill(true);
    }
}
