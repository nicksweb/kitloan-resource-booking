// Alpine ships bundled with Livewire 4; this file is loaded via @vite in the
// document <head>, before @livewireScripts starts Alpine, so registering data
// components against `alpine:init` here is safe.

document.addEventListener('alpine:init', () => {
    /**
     * Lightweight searchable single-select (combobox). Backed by a Livewire
     * property via @entangle so it drops into any Livewire form in place of a
     * native <select> when the option list is long enough to be annoying.
     *
     * config: { selected (entangled ref), options: [{value,label}], placeholder }
     */
    window.Alpine.data('searchableSelect', (config) => ({
        open: false,
        query: '',
        highlighted: 0,
        selected: config.selected,
        options: config.options || [],
        placeholder: config.placeholder || 'Select…',

        filtered() {
            const q = this.query.trim().toLowerCase();
            if (!q) return this.options;
            return this.options.filter((o) => o.label.toLowerCase().includes(q));
        },

        selectedLabel() {
            const match = this.options.find((o) => String(o.value) === String(this.selected));
            return match ? match.label : this.placeholder;
        },

        toggle() {
            this.open ? this.close() : this.openMenu();
        },

        openMenu() {
            this.open = true;
            this.query = '';
            this.highlighted = Math.max(
                0,
                this.filtered().findIndex((o) => String(o.value) === String(this.selected)),
            );
            this.$nextTick(() => this.$refs.search && this.$refs.search.focus());
        },

        close() {
            this.open = false;
        },

        move(delta) {
            if (!this.open) {
                this.openMenu();
                return;
            }
            const count = this.filtered().length;
            if (count === 0) return;
            this.highlighted = (this.highlighted + delta + count) % count;
            this.$nextTick(() => this.scrollHighlightedIntoView());
        },

        scrollHighlightedIntoView() {
            const list = this.$refs.list;
            if (!list) return;
            const el = list.children[this.highlighted];
            if (el && el.scrollIntoView) el.scrollIntoView({ block: 'nearest' });
        },

        chooseHighlighted() {
            const opt = this.filtered()[this.highlighted];
            if (opt) this.choose(opt.value);
        },

        choose(value) {
            this.selected = value;
            this.close();
            this.$nextTick(() => this.$refs.button && this.$refs.button.focus());
        },

        clear() {
            this.selected = null;
            this.close();
        },
    }));
});
