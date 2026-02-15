import gsap from 'gsap';

/**
 * Reusable GSAP animation functions for game phase transitions.
 * All animations clear inline styles on completion so CSS classes take over.
 */
export function useGameAnimations() {

    /**
     * Fade + slide-up entrance for a new phase container.
     */
    function animatePhaseIn(el) {
        if (!el) return;
        gsap.from(el, {
            y: 20,
            opacity: 0,
            duration: 0.35,
            ease: 'power2.out',
            clearProps: 'all',
        });
    }

    /**
     * Acronym letters pop in with stagger.
     * Targets children with class `.acronym-letter` inside the container.
     */
    function staggerLetters(containerEl) {
        if (!containerEl) return;
        const letters = containerEl.querySelectorAll('.acronym-letter');
        if (!letters.length) return;
        gsap.from(letters, {
            scale: 0,
            opacity: 0,
            duration: 0.4,
            stagger: 0.06,
            ease: 'back.out(1.7)',
            clearProps: 'all',
        });
    }

    /**
     * Cards (vote answers, result entries) slide up + fade with stagger.
     * Returns total duration so callers can sequence follow-up animations.
     */
    function staggerCards(containerEl, selector, delay = 0) {
        if (!containerEl) return 0;
        const cards = containerEl.querySelectorAll(selector);
        if (!cards.length) return 0;
        gsap.from(cards, {
            y: 20,
            opacity: 0,
            duration: 0.4,
            stagger: 0.08,
            delay,
            ease: 'power2.out',
            clearProps: 'all',
        });
        return delay + 0.4 + (cards.length - 1) * 0.08;
    }

    /**
     * Score rows fade + slide right with stagger.
     */
    function staggerRows(containerEl, selector, delay = 0) {
        if (!containerEl) return;
        const rows = containerEl.querySelectorAll(selector);
        if (!rows.length) return;
        gsap.from(rows, {
            x: -15,
            opacity: 0,
            duration: 0.3,
            stagger: 0.05,
            delay,
            ease: 'power2.out',
            clearProps: 'all',
        });
    }

    /**
     * Subtle content swap — short fade + tiny slide for in-place swaps
     * (e.g. answer input ↔ submitted answer).
     */
    function animateSwap(el) {
        if (!el) return;
        gsap.from(el, {
            y: 8,
            opacity: 0,
            duration: 0.25,
            ease: 'power2.out',
            clearProps: 'all',
        });
    }

    /**
     * Subtle heartbeat pulse on an element (e.g. unread badge).
     */
    function pulse(el) {
        if (!el) return;
        gsap.fromTo(el, { scale: 1 }, {
            scale: 1.3,
            duration: 0.15,
            yoyo: true,
            repeat: 1,
            ease: 'power2.inOut',
            clearProps: 'all',
        });
    }

    return {
        animatePhaseIn,
        staggerLetters,
        staggerCards,
        staggerRows,
        animateSwap,
        pulse,
    };
}
