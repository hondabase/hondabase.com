// Site-wide JS entry. The heavy rich-text editor lives in its own entry, so keep reader behaviour
// here dependency-free and small.
document.addEventListener('alpine:init', () => {
    window.Alpine.data('articleCarousel', (total) => ({
        total,
        current: 0,
        frame: null,
        go(index) {
            const next = Math.max(0, Math.min(this.total - 1, index));
            const track = this.$refs.track;
            const slide = track?.children[next];
            if (!track || !slide) return;
            track.scrollTo({
                left: slide.offsetLeft,
                behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
            });
            this.current = next;
        },
        previous() { this.go(this.current - 1); },
        next() { this.go(this.current + 1); },
        syncFromScroll() {
            cancelAnimationFrame(this.frame);
            this.frame = requestAnimationFrame(() => {
                const track = this.$refs.track;
                if (!track?.clientWidth) return;
                this.current = Math.max(0, Math.min(this.total - 1, Math.round(track.scrollLeft / track.clientWidth)));
            });
        },
        destroy() { cancelAnimationFrame(this.frame); },
    }));

    window.Alpine.data('articleFind', () => ({
        open: false,
        q: '',
        count: 0,
        current: -1,
        hits: [],
        prose: null,
        init() {
            this.prose = this.$root.closest('article')?.querySelector('.prose-article') || null;
        },
        destroy() { this.clear(); },
        toggle() {
            this.open = !this.open;
            if (this.open) this.$nextTick(() => this.$refs.input && this.$refs.input.focus());
            else this.close();
        },
        close() { this.open = false; this.q = ''; this.clear(); this.count = 0; this.current = -1; },
        clear() {
            if (!this.prose) return;
            this.prose.querySelectorAll('mark.find-hit').forEach((m) => m.replaceWith(document.createTextNode(m.textContent)));
            this.prose.normalize();
            this.hits = [];
        },
        run() {
            this.clear();
            this.current = -1;
            const term = this.q.trim();
            if (term.length < 2 || !this.prose) { this.count = 0; return; }
            const esc = term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            const walker = document.createTreeWalker(this.prose, NodeFilter.SHOW_TEXT, {
                acceptNode: (n) => n.nodeValue.trim() && !(n.parentElement && n.parentElement.closest('script,style'))
                    ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_REJECT,
            });
            const nodes = [];
            let n;
            while ((n = walker.nextNode())) nodes.push(n);
            nodes.forEach((node) => {
                const text = node.nodeValue;
                const rx = new RegExp(esc, 'gi');
                if (!rx.test(text)) return;
                rx.lastIndex = 0;
                const frag = document.createDocumentFragment();
                let last = 0, m;
                while ((m = rx.exec(text))) {
                    if (m.index > last) frag.appendChild(document.createTextNode(text.slice(last, m.index)));
                    const mark = document.createElement('mark');
                    mark.className = 'find-hit';
                    mark.textContent = m[0];
                    frag.appendChild(mark);
                    last = m.index + m[0].length;
                }
                if (last < text.length) frag.appendChild(document.createTextNode(text.slice(last)));
                node.replaceWith(frag);
            });
            this.hits = Array.from(this.prose.querySelectorAll('mark.find-hit'));
            this.count = this.hits.length;
            if (this.count) { this.current = 0; this.activate(0); }
        },
        activate(i) {
            this.hits.forEach((h) => h.classList.remove('is-active'));
            const h = this.hits[i];
            if (h) { h.classList.add('is-active'); h.scrollIntoView({ block: 'center', behavior: 'smooth' }); }
        },
        next() { if (!this.count) return; this.current = (this.current + 1) % this.count; this.activate(this.current); },
        prev() { if (!this.count) return; this.current = (this.current - 1 + this.count) % this.count; this.activate(this.current); },
        label() { return this.count ? (this.current + 1) + '/' + this.count : (this.q.trim().length >= 2 ? 'No matches' : ''); },
    }));
});

document.addEventListener('click', (event) => {
    const link = event.target.closest?.('a[data-article-link-counter]');
    if (!link) return;

    const counter = link.getAttribute('data-article-link-counter');
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (!counter || !token || !window.fetch) return;

    fetch(`/_click/article-links/${counter}`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': token,
            'Accept': 'application/json',
        },
        credentials: 'same-origin',
        keepalive: true,
    }).catch(() => {});
});
