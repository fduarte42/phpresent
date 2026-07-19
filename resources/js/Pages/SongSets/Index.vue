<script setup lang="ts">
import { NCard, NInput, NSpace } from 'naive-ui';
import { onMounted, ref } from 'vue';
import { useSongSetsStore } from '@/stores/useSongSetsStore';
import SongSetTable from '@/Components/SongSetTable.vue';
import type { SongSet } from '@/types/songSet';

const props = defineProps<{ songSets: SongSet[] }>();

const store = useSongSetsStore();
const query = ref('');

onMounted(() => {
    store.setInitial(props.songSets);
});

let debounceHandle: ReturnType<typeof setTimeout> | undefined;
function onSearch(value: string): void {
    query.value = value;
    clearTimeout(debounceHandle);
    debounceHandle = setTimeout(() => {
        void store.search(value);
    }, 250);
}
</script>

<template>
    <n-space vertical size="large">
        <n-card title="Song Sets">
            <n-space vertical>
                <n-input
                    :value="query"
                    placeholder="Search by name..."
                    clearable
                    @update:value="onSearch"
                />
                <song-set-table :song-sets="store.songSets" :loading="store.isLoading" />
            </n-space>
        </n-card>
    </n-space>
</template>
