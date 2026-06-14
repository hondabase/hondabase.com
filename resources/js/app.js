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
});
