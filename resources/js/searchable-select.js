/**
 * Ô chọn một giá trị trong danh sách dài, có ô tìm kiếm.
 *
 * `<select>` thuần không lọc được, còn danh mục ngân hàng thì gần năm mươi
 * dòng — cuộn tay trên điện thoại là cực hình. Component này giữ đúng ràng
 * buộc của một select (chỉ chọn được thứ có trong danh sách, giá trị nằm
 * trong một `<input type="hidden">` nên form gửi đi như thường) và thêm phần
 * lọc theo từ khóa.
 */

/**
 * Bỏ dấu tiếng Việt để tìm kiếm không bắt người ta gõ đúng dấu.
 *
 * `đ` không phải là `d` cộng dấu phụ nên tách tổ hợp NFD không đụng tới nó;
 * phải thay riêng, nếu không gõ "dong a" sẽ không ra "Đông Á".
 */
const plain = (value) => String(value ?? '')
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .replace(/đ/g, 'd')
    .replace(/Đ/g, 'D')
    .toLowerCase()
    .trim();

export const searchableSelect = ({ value = '', options = [] } = {}) => ({
    open: false,
    query: '',
    value,
    activeIndex: 0,

    // Chuỗi tìm kiếm tính sẵn một lần, vì mỗi ký tự gõ vào là một lần duyệt
    // toàn bộ danh sách.
    options: options.map((option) => ({
        ...option,
        haystack: plain(`${option.label} ${option.hint ?? ''}`),
    })),

    get filtered() {
        const needle = plain(this.query);

        if (needle === '') {
            return this.options;
        }

        // Mỗi từ phải khớp, nhưng không cần đúng thứ tự: "viet ngoai" vẫn ra
        // "Ngân hàng TMCP Ngoại thương Việt Nam".
        const words = needle.split(/\s+/);

        return this.options.filter((option) => words.every((word) => option.haystack.includes(word)));
    },

    get selected() {
        return this.options.find((option) => option.value === this.value) ?? null;
    },

    get label() {
        return this.selected?.label ?? '';
    },

    toggle() {
        return this.open ? this.close() : this.show();
    },

    show() {
        this.open = true;
        this.query = '';
        this.activeIndex = Math.max(0, this.options.findIndex((option) => option.value === this.value));

        this.$nextTick(() => {
            this.$refs.search?.focus();
            this.scrollToActive();
        });
    },

    close() {
        this.open = false;
    },

    /** Đóng rồi trả con trỏ về nút, để bàn phím không bị rơi ra đầu trang. */
    dismiss() {
        if (!this.open) {
            return;
        }

        this.close();
        this.$refs.control?.focus();
    },

    choose(option) {
        this.value = option.value;
        this.dismiss();
    },

    clear() {
        this.value = '';
        this.dismiss();
    },

    filter() {
        this.activeIndex = 0;
    },

    move(step) {
        const count = this.filtered.length;

        if (count === 0) {
            return;
        }

        this.activeIndex = (this.activeIndex + step + count) % count;
        this.$nextTick(() => this.scrollToActive());
    },

    pickActive() {
        const option = this.filtered[this.activeIndex];

        if (option) {
            this.choose(option);
        }
    },

    scrollToActive() {
        this.$refs.list?.children[this.activeIndex]?.scrollIntoView({ block: 'nearest' });
    },
});
