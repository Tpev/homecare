@props(['theme' => 'gift', 'id' => 'lolo-reviews'])

@once
    @push('head')
        <style>
            .lolo-reviews { --reviews-serif: ChmSerif, Georgia, serif; --reviews-green: var(--green, #23483f); --reviews-muted: #57685e; background: #fffcf6; color: var(--reviews-green); padding-block: 52px; }
            .lolo-reviews .lolo-reviews-inner { max-width: 1320px; padding-inline: 60px; margin-inline: auto; }
            .lolo-reviews .lolo-reviews-heading { display: flex; align-items: center; justify-content: space-between; gap: 24px; margin-bottom: 26px; }
            .lolo-reviews .lolo-reviews-heading h2 { margin: 0; font-family: var(--reviews-serif); font-weight: 500; font-size: clamp(30px, 3vw, 40px); line-height: 1.08; letter-spacing: -.045em; color: var(--reviews-green); }
            .lolo-reviews .lolo-all-reviews { display: inline-flex; flex-shrink: 0; gap: 10px; font-size: 12px; font-weight: 600; margin: 0; min-height: 44px; align-items: center; color: var(--reviews-green); text-decoration: underline; text-underline-offset: 5px; }
            .lolo-reviews .lolo-review-track { display: flex; gap: 20px; overflow-x: auto; scroll-snap-type: x mandatory; scroll-padding-inline: 3px; overscroll-behavior-x: contain; scrollbar-width: thin; scrollbar-color: #a8b9a3 transparent; padding: 3px 3px 14px; }
            .lolo-reviews .lolo-review-card { flex: 0 0 calc((100% - 40px) / 3); min-width: 0; margin: 0; scroll-snap-align: start; display: flex; flex-direction: column; border: 1px solid #d5ddcc; border-radius: 14px; padding: 25px; background: #fff7ea; }
            .lolo-reviews .lolo-review-rating { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; color: var(--reviews-green); font-size: 18px; letter-spacing: 3px; }
            .lolo-reviews .lolo-review-source { color: var(--reviews-muted); font-size: 10px; letter-spacing: 0; }
            .lolo-reviews .lolo-review-card blockquote { margin: 22px 0 24px; }
            .lolo-reviews .lolo-review-card blockquote p { margin: 0; font-family: var(--reviews-serif); font-size: 27px; line-height: 1.35; letter-spacing: -.025em; }
            .lolo-reviews .lolo-review-card figcaption { display: grid; grid-template-columns: 34px minmax(0, 1fr); gap: 0 10px; align-items: center; margin-top: auto; font-size: 12px; }
            .lolo-reviews .lolo-review-avatar { display: grid; place-items: center; width: 34px; height: 34px; border-radius: 50%; background: #e8ecdf; color: var(--reviews-green); font-size: 10px; font-weight: 600; }
            .lolo-reviews .lolo-review-card figcaption strong { display: block; font-weight: 600; overflow-wrap: anywhere; }
            .lolo-reviews .lolo-review-card time { display: block; color: var(--reviews-muted); font-size: 10px; margin-top: 3px; }
            .lolo-reviews .lolo-review-card figcaption a { grid-column: 2; min-height: 44px; display: inline-flex; align-items: center; gap: 6px; font-size: 11px; color: var(--reviews-green); text-decoration: underline; text-underline-offset: 5px; }
            .lolo-reviews .lolo-review-controls { display: flex; align-items: center; justify-content: flex-end; gap: 10px; margin-top: 14px; }
            .lolo-reviews .lolo-review-controls[hidden] { display: none; }
            .lolo-reviews .lolo-review-position { font-size: 11px; color: var(--reviews-muted); margin-right: 8px; }
            .lolo-reviews .lolo-review-arrow { display: inline-grid; place-items: center; width: 44px; height: 44px; padding: 0; border-radius: 50%; border: 1px solid #a8b9a3; background: transparent; color: var(--reviews-green); font-family: inherit; font-size: 22px; line-height: 1; cursor: pointer; }
            .lolo-reviews .lolo-review-arrow:hover:not(:disabled) { background: #e4eadc; }
            .lolo-reviews .lolo-review-arrow:disabled { opacity: .35; cursor: default; }
            .lolo-reviews .lolo-review-arrow:focus-visible, .lolo-reviews .lolo-review-track:focus-visible, .lolo-reviews a:focus-visible { outline: 3px solid var(--coral, #ad503d); outline-offset: 3px; }
            .lolo-reviews--home { --reviews-serif: 'Fraunces', Georgia, serif; margin-top: 80px; scroll-margin-top: 108px; }
            .lolo-reviews--home .lolo-reviews-inner { max-width: 1440px; padding-inline: max(5vw, 36px); }
            @media (max-width: 1100px) { .lolo-reviews:not(.lolo-reviews--home) .lolo-reviews-inner { padding-inline: 36px; } }
            @media (max-width: 960px) { .lolo-reviews .lolo-review-card { flex-basis: calc((100% - 20px) / 2); } }
            @media (max-width: 800px) {
                .lolo-reviews .lolo-reviews-inner, .lolo-reviews:not(.lolo-reviews--home) .lolo-reviews-inner { padding-inline: 24px; }
                .lolo-reviews--home { margin-top: 48px; }
                .lolo-reviews--home .lolo-reviews-inner { padding-inline: 22px; }
            }
            @media (max-width: 600px) {
                .lolo-reviews { padding-block: 38px; }
                .lolo-reviews .lolo-reviews-heading { display: block; margin-bottom: 20px; }
                .lolo-reviews .lolo-all-reviews { margin-top: 8px; }
                .lolo-reviews .lolo-review-card { flex-basis: 100%; padding: 23px; }
            }
        </style>
    @endpush
@endonce

@php($reviews = config('marketing_social_proof.reviews', []))
@if(count($reviews))
<section {{ $attributes->class(['lolo-reviews', 'lolo-reviews--home' => $theme === 'home']) }} id="{{ $id }}" aria-labelledby="{{ $id }}-title">
    <div class="lolo-reviews-inner">
        <div class="lolo-reviews-heading">
            <h2 id="{{ $id }}-title">Reviews on Trustpilot</h2>
            <a class="lolo-all-reviews" href="{{ config('marketing_social_proof.trustpilot_url') }}" target="_blank" rel="noopener noreferrer">Read all on Trustpilot <span aria-hidden="true">↗</span></a>
        </div>
        <div class="lolo-review-track" id="{{ $id }}-track" tabindex="0" role="region" aria-label="LoLo Care reviews. Scroll to explore, or use the previous and next buttons.">
            @foreach($reviews as $review)
                <figure class="lolo-review-card" aria-label="Review {{ $loop->iteration }} of {{ $loop->count }}">
                    <div class="lolo-review-rating" role="img" aria-label="{{ $review['rating'] }} out of 5 stars"><span aria-hidden="true">★★★★★</span><span class="lolo-review-source">Trustpilot</span></div>
                    <blockquote><p>“{{ $review['excerpt'] }}”</p></blockquote>
                    <figcaption>
                        <span class="lolo-review-avatar" aria-hidden="true">{{ $review['initials'] }}</span>
                        <span><strong>{{ $review['name'] }}</strong><time datetime="{{ $review['published_at'] }}">{{ $review['published_label'] }}</time></span>
                        <a href="{{ $review['url'] }}" target="_blank" rel="noopener noreferrer" aria-label="Read {{ $review['name'] }}’s full review on Trustpilot (opens in a new tab)">Read full review <span aria-hidden="true">↗</span></a>
                    </figcaption>
                </figure>
            @endforeach
        </div>
        <div class="lolo-review-controls" hidden>
            <span class="lolo-review-position" aria-live="polite" aria-atomic="true"></span>
            <button class="lolo-review-arrow" type="button" data-review-previous aria-label="Previous reviews" aria-controls="{{ $id }}-track"><span aria-hidden="true">←</span></button>
            <button class="lolo-review-arrow" type="button" data-review-next aria-label="Next reviews" aria-controls="{{ $id }}-track"><span aria-hidden="true">→</span></button>
        </div>
    </div>
</section>
<script>
    (() => {
        const section = document.currentScript.previousElementSibling;
        const track = section.querySelector('.lolo-review-track');
        const cards = [...track.children];
        const controls = section.querySelector('.lolo-review-controls');
        const previous = controls.querySelector('[data-review-previous]');
        const next = controls.querySelector('[data-review-next]');
        const position = controls.querySelector('.lolo-review-position');
        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
        const update = () => {
            const bounds = track.getBoundingClientRect();
            const visible = cards.map((card, index) => ({ index, bounds: card.getBoundingClientRect() }))
                .filter(card => card.bounds.left < bounds.right - card.bounds.width / 2 && card.bounds.right > bounds.left + card.bounds.width / 2);
            previous.disabled = track.scrollLeft <= 2;
            next.disabled = track.scrollLeft >= track.scrollWidth - track.clientWidth - 2;
            controls.hidden = track.scrollWidth <= track.clientWidth + 2;
            if (visible.length) {
                const first = visible[0].index + 1;
                const last = visible[visible.length - 1].index + 1;
                position.textContent = `Reviews ${first === last ? first : first + '–' + last} of ${cards.length}`;
            }
        };
        const move = direction => track.scrollBy({
            left: direction * (cards[0].getBoundingClientRect().width + parseFloat(getComputedStyle(track).columnGap)),
            behavior: reducedMotion.matches ? 'instant' : 'smooth',
        });
        previous.addEventListener('click', () => move(-1));
        next.addEventListener('click', () => move(1));
        track.addEventListener('keydown', event => {
            if (event.target !== track || !['ArrowLeft', 'ArrowRight'].includes(event.key)) return;
            event.preventDefault();
            move(event.key === 'ArrowRight' ? 1 : -1);
        });
        track.addEventListener('scroll', update, { passive: true });
        window.addEventListener('resize', update);
        if ('ResizeObserver' in window) new ResizeObserver(update).observe(track);
        update();
    })();
</script>
@endif
