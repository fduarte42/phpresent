<script setup lang="ts">
import { NConfigProvider, NLayout, NLayoutContent, NLayoutHeader, darkTheme } from 'naive-ui';
import { usePreferredDark } from '@vueuse/core';
import { computed } from 'vue';

const prefersDark = usePreferredDark();
const theme = computed(() => (prefersDark.value ? darkTheme : null));
</script>

<template>
    <n-config-provider :theme="theme">
        <n-layout style="min-height: 100vh">
            <n-layout-header style="padding: 12px 20px; font-weight: 600; font-size: 1.1rem">
                Phpresent
            </n-layout-header>
            <n-layout-content style="padding: 20px">
                <slot />
            </n-layout-content>
        </n-layout>
    </n-config-provider>
</template>
