{{-- Use x-on:* (not @*) for Alpine events: article HTML is re-parsed by DOMDocument
     in ArticleClickCounter, which strips attributes whose names start with @. --}}
<section class="article-carousel"
    aria-roledescription="carousel"
    aria-label="Image carousel"
    x-data="articleCarousel({{ count($slides) }})"
    x-on:keydown.left.prevent="previous()"
    x-on:keydown.right.prevent="next()"
    x-on:keydown.escape.window="closeLightbox()">
    <div class="carousel-track" x-ref="track" x-on:scroll.passive="syncFromScroll()" tabindex="0">
        @foreach ($slides as $index => $slide)
            <figure class="carousel-slide"
                aria-roledescription="slide"
                aria-label="{{ $index + 1 }} of {{ count($slides) }}">
                <button type="button"
                    class="carousel-zoom"
                    x-on:click="openLightbox({{ $index }})"
                    aria-label="View image fullscreen">
                    <img src="{{ $slide['src'] }}" alt="{{ $slide['alt'] }}" loading="lazy">
                </button>
                @if ($slide['caption'] !== '')
                    <figcaption>{{ $slide['caption'] }}</figcaption>
                @endif
            </figure>
        @endforeach
    </div>
    <div class="carousel-controls">
        <button type="button" x-on:click="previous()" :disabled="current === 0" aria-label="Previous slide">&#8249;</button>
        <span aria-live="polite"><span x-text="current + 1">1</span> / {{ count($slides) }}</span>
        <button type="button" x-on:click="next()" :disabled="current === total - 1" aria-label="Next slide">&#8250;</button>
    </div>

    <div class="carousel-lightbox"
        x-show="lightbox"
        x-cloak
        x-transition.opacity
        role="dialog"
        aria-modal="true"
        aria-label="Fullscreen image"
        x-on:click.self="closeLightbox()">
        <button type="button" class="carousel-lightbox-close" x-on:click="closeLightbox()" aria-label="Close fullscreen">&#10005;</button>
        <button type="button"
            class="carousel-lightbox-nav carousel-lightbox-prev"
            x-on:click="previous()"
            :disabled="current === 0"
            aria-label="Previous slide">&#8249;</button>
        <figure class="carousel-lightbox-stage">
            <img :src="lightboxSrc()" :alt="lightboxAlt()">
            <figcaption x-show="lightboxCaption()" x-text="lightboxCaption()"></figcaption>
        </figure>
        <button type="button"
            class="carousel-lightbox-nav carousel-lightbox-next"
            x-on:click="next()"
            :disabled="current === total - 1"
            aria-label="Next slide">&#8250;</button>
        <div class="carousel-lightbox-counter" aria-live="polite">
            <span x-text="current + 1">1</span> / {{ count($slides) }}
        </div>
    </div>
</section>
