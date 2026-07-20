<script setup lang="ts">
import { NConfigProvider, NLayout, NLayoutContent, NLayoutHeader, NSpace, darkTheme } from 'naive-ui';
import { usePreferredDark } from '@vueuse/core';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const prefersDark = usePreferredDark();
const theme = computed(() => (prefersDark.value ? darkTheme : null));

const navLinks = [
    { href: '/songs', label: 'Songs' },
    { href: '/songsets', label: 'Song Sets' },
    { href: '/displays', label: 'Displays' },
    { href: '/presentation', label: 'Live Control' },
    { href: '/media', label: 'Media' },
];
</script>

<template>
    <n-config-provider :theme="theme">
        <n-layout style="min-height: 100vh">
            <n-layout-header
                style="
                    padding: 12px 20px;
                    display: flex;
                    align-items: center;
                    gap: 24px;
                    font-weight: 600;
                    font-size: 1.1rem;
                "
            >
                <span>Phpresent</span>
                <n-space size="large" style="font-size: 0.95rem; font-weight: 500">
                    <Link v-for="link in navLinks" :key="link.href" :href="link.href">{{ link.label }}</Link>
                </n-space>
            </n-layout-header>
            <n-layout-content style="padding: 20px">
                <slot />
            </n-layout-content>
        </n-layout>
    </n-config-provider>
</template>
