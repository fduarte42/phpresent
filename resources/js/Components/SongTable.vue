<script setup lang="ts">
import { NDataTable } from 'naive-ui';
import type { DataTableColumns } from 'naive-ui';
import { computed } from 'vue';
import type { Song } from '@/types/song';

const props = defineProps<{ songs: Song[]; loading: boolean }>();

const columns = computed<DataTableColumns<Song>>(() => [
    { title: 'Title', key: 'title', sorter: 'default' },
    {
        title: 'Authors',
        key: 'authors',
        render: (row) => row.authors.join(', '),
    },
    { title: 'CCLI', key: 'ccli' },
    { title: 'Key', key: 'defaultKey' },
    {
        title: 'Tags',
        key: 'tags',
        render: (row) => row.tags.join(', '),
    },
]);

const rowKey = (row: Song) => row.id;
</script>

<template>
    <n-data-table
        :columns="columns"
        :data="props.songs"
        :loading="props.loading"
        :row-key="rowKey"
        :pagination="{ pageSize: 20 }"
    />
</template>
