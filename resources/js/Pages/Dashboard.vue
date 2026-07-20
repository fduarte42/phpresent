<script setup lang="ts">
import { NCard, NSpace } from 'naive-ui';
import { Link } from '@inertiajs/vue3';

const areas = [
    { href: '/songs', label: 'Songs', description: 'Song catalogue synced from SongbookPro.' },
    { href: '/songsets', label: 'Song Sets', description: 'Ordered sets/setlists for a service.' },
    { href: '/presentation', label: 'Live Control', description: 'Operator screen for live presentation.' },
    { href: '/displays', label: 'Displays', description: 'Manage connected output displays.' },
    { href: '/media', label: 'Media', description: 'Images, video, and audio assets.' },
    { href: '/themes', label: 'Themes', description: 'Global, song, and section-scoped visual styles.' },
    { href: '/bible', label: 'Bible', description: 'Search scripture and manage bookmarks.' },
    { href: '/users', label: 'Users & Roles', description: 'Manage accounts and permissions.' },
];
</script>

<template>
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 16px">
        <Link v-for="area in areas" :key="area.href" :href="area.href" style="text-decoration: none; color: inherit">
            <n-card :title="area.label" hoverable>
                {{ area.description }}
            </n-card>
        </Link>
    </div>
</template>
