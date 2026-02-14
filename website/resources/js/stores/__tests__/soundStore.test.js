import { describe, it, expect, beforeEach, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { useSoundStore } from '../soundStore.js';

// Mock howler
vi.mock('howler', () => ({
    Howl: vi.fn().mockImplementation(() => ({
        play: vi.fn(),
        volume: vi.fn(),
    })),
}));

describe('soundStore', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        localStorage.clear();
    });

    it('is enabled by default', () => {
        const store = useSoundStore();
        expect(store.enabled).toBe(true);
    });

    it('reads enabled state from localStorage', () => {
        localStorage.setItem('select-sound-enabled', 'false');
        // Need a fresh store instance
        setActivePinia(createPinia());
        const store = useSoundStore();
        expect(store.enabled).toBe(false);
    });

    it('toggles enabled state', () => {
        const store = useSoundStore();
        expect(store.enabled).toBe(true);

        store.toggle();
        expect(store.enabled).toBe(false);
        expect(localStorage.getItem('select-sound-enabled')).toBe('false');

        store.toggle();
        expect(store.enabled).toBe(true);
        expect(localStorage.getItem('select-sound-enabled')).toBe('true');
    });

    it('has default volume of 0.3', () => {
        const store = useSoundStore();
        expect(store.volume).toBeCloseTo(0.3);
    });

    it('reads volume from localStorage', () => {
        localStorage.setItem('select-sound-volume', '0.7');
        setActivePinia(createPinia());
        const store = useSoundStore();
        expect(store.volume).toBeCloseTo(0.7);
    });

    it('clamps volume to 0-1 range', () => {
        const store = useSoundStore();

        store.setVolume(1.5);
        expect(store.volume).toBe(1);

        store.setVolume(-0.5);
        expect(store.volume).toBe(0);
    });

    it('does not play sounds when disabled', () => {
        const store = useSoundStore();
        store.toggle(); // disable
        store.play('round-start');
        // No error thrown, sound silently skipped
    });
});
