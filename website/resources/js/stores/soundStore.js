import { defineStore } from 'pinia';
import { ref } from 'vue';

const STORAGE_KEY = 'select-sound-enabled';
const VOLUME_KEY = 'select-sound-volume';

export const useSoundStore = defineStore('sound', () => {
    const enabled = ref(localStorage.getItem(STORAGE_KEY) !== 'false');
    const volume = ref(parseFloat(localStorage.getItem(VOLUME_KEY) || '0.3'));
    const sounds = {};

    function toggle() {
        enabled.value = !enabled.value;
        localStorage.setItem(STORAGE_KEY, String(enabled.value));
    }

    function setVolume(v) {
        volume.value = Math.max(0, Math.min(1, v));
        localStorage.setItem(VOLUME_KEY, String(volume.value));
        Object.values(sounds).forEach((howl) => {
            if (howl) howl.volume(volume.value);
        });
    }

    function _loadSound(name) {
        if (sounds[name]) return sounds[name];

        // Lazy-load Howler only when needed
        return import('howler').then(({ Howl }) => {
            const soundMap = {
                'round-start': '/sounds/round-start.mp3',
                'time-warning': '/sounds/time-warning.mp3',
                'time-up': '/sounds/time-up.mp3',
                'vote-reveal': '/sounds/vote-reveal.mp3',
                'game-win': '/sounds/game-win.mp3',
                'player-join': '/sounds/player-join.mp3',
                'chat-message': '/sounds/chat-message.mp3',
            };

            const src = soundMap[name];
            if (!src) return null;

            const howl = new Howl({
                src: [src],
                volume: volume.value,
                preload: false,
            });

            sounds[name] = howl;
            return howl;
        });
    }

    function play(name) {
        if (!enabled.value) return;

        const existing = sounds[name];
        if (existing) {
            existing.volume(volume.value);
            existing.play();
            return;
        }

        // Lazy load and play
        _loadSound(name).then((howl) => {
            if (howl) {
                howl.volume(volume.value);
                howl.play();
            }
        });
    }

    return {
        enabled,
        volume,
        toggle,
        setVolume,
        play,
    };
});
