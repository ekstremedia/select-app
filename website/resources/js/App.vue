<template>
    <router-view v-slot="{ Component, route }">
        <AppLayout v-if="route.name !== 'game' && route.name !== 'game-spectate' && route.name !== 'welcome'">
            <component :is="Component" />
        </AppLayout>
        <component v-else :is="Component" />
    </router-view>
</template>

<script setup>
import { onMounted } from 'vue';
import { useAuthStore } from './stores/authStore.js';
import AppLayout from './layouts/AppLayout.vue';

const authStore = useAuthStore();

onMounted(async () => {
    await authStore.loadFromStorage();
});
</script>
