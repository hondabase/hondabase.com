<section class="article-carousel"
    aria-roledescription="carousel"
    aria-label="Image carousel"
    x-data="articleCarousel({{ count($slides) }})"
    @keydown.left.prevent="previous()"
    @keydown.right.prevent="next()">
    <div class="carousel-track" x-ref="track" @scroll.passive="syncFromScroll()" tabindex="0">
        @foreach ($slides as $index => $slide)
            <figure class="carousel-slide"
                aria-roledescription="slide"
                aria-label="{{ $index + 1 }} of {{ count($slides) }}">
                <img src="{{ $slide['src'] }}" alt="{{ $slide['alt'] }}" loading="lazy">
                @if ($slide['caption'] !== '')
                    <figcaption>{{ $slide['caption'] }}</figcaption>
                @endif
            </figure>
        @endforeach
    </div>
    <div class="carousel-controls">
        <button type="button" @click="previous()" :disabled="current === 0" aria-label="Previous slide">&#8249;</button>
        <span aria-live="polite"><span x-text="current + 1">1</span> / {{ count($slides) }}</span>
        <button type="button" @click="next()" :disabled="current === total - 1" aria-label="Next slide">&#8250;</button>
    </div>
</section>
