/* Hondabase GA4 helpers: SPA page views (wire:navigate) + article-aware events. */
(function () {
    function sendArticleView() {
        var el = document.querySelector('[data-ga-article]');
        if (!el || !window.gtag) return;
        gtag('event', 'article_view', {
            article: el.getAttribute('data-ga-article') || '',
            category: el.getAttribute('data-ga-category') || '',
            vehicle_type: el.getAttribute('data-ga-type') || '',
            complexity: el.getAttribute('data-ga-complexity') || '',
            obd: el.getAttribute('data-ga-obd') || '',
            engine: el.getAttribute('data-ga-engine') || '',
            tags: el.getAttribute('data-ga-tags') || ''
        });
    }

    // Initial load: gtag('config') already sent page_view, so only add the article event.
    if (document.readyState !== 'loading') sendArticleView();
    else document.addEventListener('DOMContentLoaded', sendArticleView);

    // Livewire SPA navigation: send a fresh page_view + article event.
    document.addEventListener('livewire:navigated', function () {
        if (window.gtag) {
            gtag('event', 'page_view', { page_location: location.href, page_title: document.title });
        }
        sendArticleView();
    });

    // Explorer search (debounced) and facet selection.
    var t;
    document.addEventListener('input', function (e) {
        var el = e.target;
        if (!window.gtag || !el.classList || !el.classList.contains('ex-input')) return;
        clearTimeout(t);
        t = setTimeout(function () {
            var v = el.value.trim();
            if (v.length >= 2) gtag('event', 'search', { search_term: v });
        }, 800);
    });
    document.addEventListener('click', function (e) {
        if (!window.gtag || !e.target.closest) return;
        var chip = e.target.closest('.chip');
        if (chip) gtag('event', 'facet_select', { facet: (chip.textContent || '').trim().replace(/\s+/g, ' ') });
    });
})();
