<script setup lang="ts">
import { NDataTable } from 'naive-ui';
import type { DataTableColumns } from 'naive-ui';
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import type { SongSet } from '@/types/songSet';

const props = defineProps<{ songSets: SongSet[]; loading: boolean }>();

const columns = computed<DataTableColumns<SongSet>>(() => [
    { title: 'Name', key: 'name', sorter: 'default' },
    {
        title: 'Service Date',
        key: 'serviceDate',
        render: (row) => (row.serviceDate ? new Date(row.serviceDate).toLocaleDateString() : '—'),
    },
    {
        title: 'Items',
        key: 'items',
        render: (row) => row.items.length,
    },
]);

const rowKey = (row: SongSet) => row.id;

function onRowClick(row: SongSet) {
    return {
        onClick: () => router.visit(`/songsets/${row.id}`),
        style: 'cursor: pointer',
    };
}
</script>

<template>
    <n-data-table
        :columns="columns"
        :data="props.songSets"
        :loading="props.loading"
        :row-key="rowKey"
        :row-props="onRowClick"
        :pagination="{ pageSize: 20 }"
    />
</template>
