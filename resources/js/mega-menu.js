/*
 * Perilaku mega menu kategori (components/category-menu, desktop). Tanpa library tambahan, hanya Alpine.
 * - Hover/fokus grup: buka setelah ~120 ms; pindah antar grup yang sudah terbuka langsung tanpa jeda.
 * - Kursor keluar: tutup setelah jeda kecil supaya tidak berkedip saat kursor melintas ke panel.
 * - Esc: tutup & kembali ke tombol grup. Klik di luar / fokus keluar menu: tutup.
 * - Panah kiri/kanan antar grup; Enter/Spasi/panah bawah masuk panel; di panel panah atas/bawah
 *   antar kategori, kanan ke subkategori, kiri kembali ke kategori.
 */
const OPEN_DELAY = 120;
const CLOSE_DELAY = 150;

export default () => ({
    open: null,
    openTimer: null,
    closeTimer: null,

    hoverOpen(key) {
        this.cancelClose();
        clearTimeout(this.openTimer);
        if (this.open !== null) {
            this.open = key;
            return;
        }
        this.openTimer = setTimeout(() => { this.open = key; }, OPEN_DELAY);
    },

    focusOpen(key) {
        this.hoverOpen(key);
    },

    openNow(key, focusPanel = false) {
        clearTimeout(this.openTimer);
        this.cancelClose();
        this.open = key;
        if (focusPanel) {
            // Tunggu panel benar-benar tampil (x-show + transisi) sebelum memindahkan fokus
            const tryFocus = (attempt = 0) => {
                const link = this.panel(key)?.querySelector('[data-cat-link]');
                if (link && link.offsetParent !== null) {
                    link.focus();
                } else if (attempt < 15) {
                    setTimeout(() => tryFocus(attempt + 1), 16);
                }
            };
            this.$nextTick(() => tryFocus());
        }
    },

    scheduleClose() {
        clearTimeout(this.openTimer);
        clearTimeout(this.closeTimer);
        this.closeTimer = setTimeout(() => { this.open = null; }, CLOSE_DELAY);
    },

    cancelClose() {
        clearTimeout(this.closeTimer);
    },

    close() {
        clearTimeout(this.openTimer);
        this.cancelClose();
        this.open = null;
    },

    closeAndFocus() {
        const key = this.open;
        this.close();
        if (key !== null) {
            this.$root.querySelector(`[data-group-btn="${CSS.escape(key)}"]`)?.focus({ preventScroll: true });
            // Fokus kembali ke tombol tidak membuka ulang panel
            clearTimeout(this.openTimer);
        }
    },

    onFocusOut(event) {
        if (!this.$root.contains(event.relatedTarget)) {
            this.close();
        }
    },

    panel(key) {
        return this.$root.querySelector(`[data-group-panel="${CSS.escape(key)}"]`);
    },

    moveGroup(event, dir) {
        const buttons = [...this.$root.querySelectorAll('[data-group-btn]')];
        const next = buttons[(buttons.indexOf(event.currentTarget) + dir + buttons.length) % buttons.length];
        next.focus();
        if (this.open !== null) {
            this.open = next.dataset.groupBtn;
        }
    },

    stepCategory(event, dir) {
        const links = [...event.currentTarget.closest('ul').querySelectorAll('[data-cat-link]')];
        links[(links.indexOf(event.currentTarget) + dir + links.length) % links.length].focus();
    },

    enterSubmenu(event) {
        const slug = event.currentTarget.dataset.catLink;
        const panel = event.currentTarget.closest('[data-group-panel]');
        panel.querySelector(`[data-cat-panel="${CSS.escape(slug)}"] a`)?.focus();
    },

    backToCategory(event) {
        const slug = event.currentTarget.dataset.catPanel;
        event.currentTarget.closest('[data-group-panel]').querySelector(`[data-cat-link="${CSS.escape(slug)}"]`)?.focus();
    },

    stepLink(event, dir) {
        const links = [...event.currentTarget.querySelectorAll('a')];
        const i = links.indexOf(document.activeElement);
        links[(i + dir + links.length) % links.length]?.focus();
    },
});
