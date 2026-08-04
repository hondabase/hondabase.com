// Site-wide JS entry. The heavy rich-text editor lives in its own entry, so keep reader behaviour
// here dependency-free and small.
document.addEventListener('alpine:init', () => {
    window.Alpine.data('articleCarousel', (total) => ({
        total,
        current: 0,
        frame: null,
        lightbox: false,
        maxZoom: 4,
        zoomScale: 1,
        zoomX: 0,
        zoomY: 0,
        pointers: {},
        dragStart: null,
        pinchStart: null,
        navigating: false,
        navTimer: null,
        go(index) {
            const next = Math.max(0, Math.min(this.total - 1, index));
            const track = this.$refs.track;
            this.resetZoom();
            if (!track?.clientWidth) {
                this.current = next;
                return;
            }
            // While the smooth scroll below is in flight, syncFromScroll() would otherwise
            // round the still-animating scrollLeft back to the old index and flash the
            // previous slide's image before settling on the target — suppress it until
            // the scroll actually finishes.
            this.navigating = true;
            clearTimeout(this.navTimer);
            this.navTimer = setTimeout(() => { this.navigating = false; }, 500);
            // Full-width slides: index * clientWidth is more reliable than offsetLeft
            // (offsetParent quirks inside flex + scroll containers).
            track.scrollTo({
                left: next * track.clientWidth,
                behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
            });
            this.current = next;
        },
        previous() { this.go(this.current - 1); },
        next() { this.go(this.current + 1); },
        onScrollEnd() {
            clearTimeout(this.navTimer);
            this.navigating = false;
            this.syncFromScroll();
        },
        syncFromScroll() {
            if (this.navigating) return;
            cancelAnimationFrame(this.frame);
            this.frame = requestAnimationFrame(() => {
                const track = this.$refs.track;
                if (!track?.clientWidth) return;
                this.current = Math.max(0, Math.min(this.total - 1, Math.round(track.scrollLeft / track.clientWidth)));
            });
        },
        slideEl(index = this.current) {
            return this.$refs.track?.children[index] ?? null;
        },
        lightboxSrc() {
            return this.slideEl()?.querySelector('img')?.currentSrc
                || this.slideEl()?.querySelector('img')?.src
                || '';
        },
        lightboxAlt() {
            return this.slideEl()?.querySelector('img')?.alt || '';
        },
        lightboxCaption() {
            return this.slideEl()?.querySelector('figcaption')?.textContent?.trim() || '';
        },
        openLightbox(index) {
            if (typeof index === 'number') this.go(index);
            this.resetZoom();
            this.lightbox = true;
            document.documentElement.classList.add('carousel-lightbox-open');
            const el = this.$refs.lightboxDialog;
            if (el?.requestFullscreen) {
                el.requestFullscreen().catch(() => {});
            }
        },
        closeLightbox() {
            if (!this.lightbox) return;
            this.lightbox = false;
            this.resetZoom();
            document.documentElement.classList.remove('carousel-lightbox-open');
            if (document.fullscreenElement === this.$refs.lightboxDialog) {
                document.exitFullscreen().catch(() => {});
            }
        },
        init() {
            this.onFullscreenChange = () => {
                if (this.lightbox && document.fullscreenElement !== this.$refs.lightboxDialog) {
                    this.closeLightbox();
                }
            };
            document.addEventListener('fullscreenchange', this.onFullscreenChange);
        },
        resetZoom() {
            this.zoomScale = 1;
            this.zoomX = 0;
            this.zoomY = 0;
            this.pointers = {};
            this.dragStart = null;
            this.pinchStart = null;
        },
        lightboxImgStyle() {
            return `transform: translate(${this.zoomX}px, ${this.zoomY}px) scale(${this.zoomScale})`;
        },
        clampPan() {
            const img = this.$refs.lightboxImg;
            if (!img || this.zoomScale <= 1) {
                this.zoomX = 0;
                this.zoomY = 0;
                return;
            }
            const maxX = Math.max(0, (img.clientWidth * (this.zoomScale - 1)) / 2);
            const maxY = Math.max(0, (img.clientHeight * (this.zoomScale - 1)) / 2);
            this.zoomX = Math.min(maxX, Math.max(-maxX, this.zoomX));
            this.zoomY = Math.min(maxY, Math.max(-maxY, this.zoomY));
        },
        setZoomAt(nextScale, clientX, clientY) {
            const img = this.$refs.lightboxImg;
            if (!img) return;
            nextScale = Math.min(this.maxZoom, Math.max(1, nextScale));
            const prevScale = this.zoomScale;
            if (nextScale === prevScale) return;
            const rect = img.getBoundingClientRect();
            const centerX = rect.left + rect.width / 2;
            const centerY = rect.top + rect.height / 2;
            const ratio = nextScale / prevScale;
            this.zoomX += (clientX - centerX) * (1 - ratio);
            this.zoomY += (clientY - centerY) * (1 - ratio);
            this.zoomScale = nextScale;
            if (this.zoomScale === 1) {
                this.zoomX = 0;
                this.zoomY = 0;
            }
            this.clampPan();
        },
        onWheel(e) {
            const factor = e.deltaY < 0 ? 1.25 : 0.8;
            this.setZoomAt(this.zoomScale * factor, e.clientX, e.clientY);
        },
        onDblClick(e) {
            this.setZoomAt(this.zoomScale > 1 ? 1 : 2.5, e.clientX, e.clientY);
        },
        pinchState() {
            const [a, b] = Object.values(this.pointers);
            return {
                dist: Math.hypot(a.x - b.x, a.y - b.y),
                midX: (a.x + b.x) / 2,
                midY: (a.y + b.y) / 2,
            };
        },
        onPointerDown(e) {
            this.pointers[e.pointerId] = { x: e.clientX, y: e.clientY };
            const ids = Object.keys(this.pointers);
            if (ids.length === 2) {
                this.dragStart = null;
                const state = this.pinchState();
                this.pinchStart = state.dist > 1 ? { ...state, scale: this.zoomScale } : null;
            } else if (ids.length === 1) {
                this.pinchStart = null;
                if (this.zoomScale > 1) {
                    e.target.setPointerCapture?.(e.pointerId);
                    this.dragStart = { x: e.clientX, y: e.clientY, zoomX: this.zoomX, zoomY: this.zoomY };
                }
            }
        },
        onPointerMove(e) {
            if (!(e.pointerId in this.pointers)) return;
            this.pointers[e.pointerId] = { x: e.clientX, y: e.clientY };
            const ids = Object.keys(this.pointers);
            if (ids.length === 2 && this.pinchStart) {
                const state = this.pinchState();
                const nextScale = this.pinchStart.scale * (state.dist / this.pinchStart.dist);
                this.setZoomAt(nextScale, state.midX, state.midY);
            } else if (ids.length === 1 && this.dragStart) {
                this.zoomX = this.dragStart.zoomX + (e.clientX - this.dragStart.x);
                this.zoomY = this.dragStart.zoomY + (e.clientY - this.dragStart.y);
                this.clampPan();
            }
        },
        onPointerUp(e) {
            delete this.pointers[e.pointerId];
            this.dragStart = null;
            this.pinchStart = null;
        },
        destroy() {
            cancelAnimationFrame(this.frame);
            clearTimeout(this.navTimer);
            document.documentElement.classList.remove('carousel-lightbox-open');
            document.removeEventListener('fullscreenchange', this.onFullscreenChange);
        },
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
