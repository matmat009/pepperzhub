import type { Directive, DirectiveBinding } from 'vue';

/**
 * Storefront scroll reveal.
 *
 * The motion itself lives in `app.css` — this only decides *when* an element
 * has earned its `data-sf-revealed` attribute. Splitting it that way is what
 * keeps the whole system one keyframe set instead of one per page.
 *
 * Used as a local directive rather than a global one: `import { vReveal }` in
 * a `<script setup>` block makes `v-reveal` available in that template, so
 * nothing has to be registered on the app instance and admin pages can never
 * reach it by accident.
 */
export type RevealVariant =
    | 'fade-up'
    | 'fade-in'
    | 'fade-left'
    | 'fade-right'
    | 'scale-in'
    /** Reveals the element's direct children on a capped ladder. */
    | 'stagger';

const REVEALED = 'data-sf-revealed';

/**
 * A quarter of the element. Later than it sounds, and deliberately so: at an
 * eighth the reveal had usually finished before the eye arrived, which read as
 * content appearing rather than animating.
 */
const RATIO = 0.25;

/**
 * Observing at several ratios rather than one is a correctness fix, not a
 * refinement. An element taller than `viewport / RATIO` can never reach a 25%
 * ratio — a one-column product grid on a phone is exactly that — so a lone
 * threshold would leave it at `opacity: 0` for the whole visit. The low stops
 * give the callback a chance to fire while the coverage rule below decides
 * whether enough of the viewport is filled to count.
 *
 * The spacing carries as much weight as the values. The callback only runs
 * when the ratio crosses a listed stop, so coverage can only ever be judged at
 * one of them, and each stop has to sit within `1 / RATIO` of the next. At the
 * 5x gap these used to have, raising RATIO to 0.25 stranded every element
 * between four and five root-heights tall: it crosses 0.05, falls short of the
 * coverage bar, and never crosses anything again. Spaced 3x, the rule holds
 * down to elements ~50x the root height. Raising RATIO past ~0.33 means
 * re-checking them.
 */
const THRESHOLDS = [0, 0.02, 0.06, 0.18, RATIO, 0.5];

/**
 * Holds the trigger back from the fold by nearly a fifth of the viewport, so a
 * section is properly inside the window before it starts. This is the half of
 * the delay that behaves the same whatever height the element happens to be.
 */
const ROOT_MARGIN = '0px 0px -18% 0px';

let observer: IntersectionObserver | null = null;

const prefersReducedMotion = () =>
    typeof window !== 'undefined' &&
    typeof window.matchMedia === 'function' &&
    window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const reveal = (el: Element) => {
    el.setAttribute(REVEALED, '');
    observer?.unobserve(el);
};

/**
 * Either a quarter of the element is on screen, or the element covers a
 * quarter of the viewport. The second clause is what rescues anything taller
 * than the screen; the first is what keeps a short element from revealing on
 * its first stray pixel.
 */
const hasArrived = (entry: IntersectionObserverEntry) => {
    const rootHeight =
        entry.rootBounds?.height ??
        (typeof window === 'undefined' ? 0 : window.innerHeight);

    return (
        entry.intersectionRatio >= RATIO ||
        entry.intersectionRect.height >= rootHeight * RATIO
    );
};

/**
 * One observer for the whole storefront rather than one per element — and no
 * scroll listener anywhere. It is never torn down: it outlives any single page
 * by design, holds no element it has not been handed, and drops each one the
 * moment that element reveals or unmounts.
 *
 * Deliberately the native API rather than @vueuse/core's
 * `useIntersectionObserver`, which builds one observer per call site and needs
 * a component scope a directive does not have. Nothing is added to the bundle
 * either way — this is the browser's own.
 */
const sharedObserver = () => {
    if (observer || typeof IntersectionObserver === 'undefined') {
        return observer;
    }

    observer = new IntersectionObserver(
        (entries) => {
            for (const entry of entries) {
                if (entry.isIntersecting && hasArrived(entry)) {
                    reveal(entry.target);
                }
            }
        },
        { threshold: THRESHOLDS, rootMargin: ROOT_MARGIN },
    );

    return observer;
};

const classesFor = (variant: RevealVariant) =>
    variant === 'stagger'
        ? ['sf-stagger']
        : variant === 'fade-up'
          ? ['sf-reveal']
          : ['sf-reveal', `sf-reveal-${variant}`];

const observe = (
    el: HTMLElement,
    binding: DirectiveBinding<RevealVariant | undefined>,
) => {
    const variant = binding.value ?? 'fade-up';

    /*
     * Reduced motion never gets the hiding class at all. Adding it and relying
     * on the media query to undo it would still leave the page one failed
     * observer away from blank content, for the viewers least able to work
     * around it.
     */
    if (prefersReducedMotion()) {
        return;
    }

    el.classList.add(...classesFor(variant));

    const io = sharedObserver();

    // No IntersectionObserver in this browser: show it, rather than hide it.
    if (!io) {
        el.setAttribute(REVEALED, '');

        return;
    }

    io.observe(el);
};

export const vReveal: Directive<HTMLElement, RevealVariant | undefined> = {
    /*
     * `mounted` runs in the same synchronous pass as the render that inserted
     * the element, before the browser has painted it — so above-the-fold
     * content is hidden from its first frame and fades in, rather than
     * appearing and then blinking out while the observer sets up.
     */
    mounted: observe,
    unmounted(el) {
        observer?.unobserve(el);
    },
};
