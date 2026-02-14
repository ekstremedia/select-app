import { ref, onMounted, onUnmounted } from 'vue';

const viewportHeight = ref(window.innerHeight);
const keyboardVisible = ref(false);
const keyboardHeight = ref(0);
const safeAreaBottom = ref(0);

let initialized = false;

function init() {
    if (initialized) return;
    initialized = true;

    function updateViewport() {
        const vv = window.visualViewport;
        if (vv) {
            viewportHeight.value = vv.height;
            const fullHeight = window.innerHeight;
            const visibleHeight = vv.height;
            const diff = fullHeight - visibleHeight;
            keyboardVisible.value = diff > 100;
            keyboardHeight.value = diff > 100 ? diff : 0;
        } else {
            viewportHeight.value = window.innerHeight;
        }

        // Update CSS custom property
        document.documentElement.style.setProperty('--vh', `${viewportHeight.value * 0.01}px`);
    }

    if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', updateViewport);
        window.visualViewport.addEventListener('scroll', updateViewport);
    } else {
        window.addEventListener('resize', updateViewport);
    }

    // Read safe area from CSS env
    const testEl = document.createElement('div');
    testEl.style.paddingBottom = 'env(safe-area-inset-bottom)';
    document.body.appendChild(testEl);
    safeAreaBottom.value = parseInt(getComputedStyle(testEl).paddingBottom) || 0;
    document.body.removeChild(testEl);

    updateViewport();
}

export function useViewport() {
    onMounted(() => {
        init();
    });

    return {
        viewportHeight,
        keyboardVisible,
        keyboardHeight,
        safeAreaBottom,
    };
}
