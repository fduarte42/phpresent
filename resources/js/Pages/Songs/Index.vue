<script setup lang="ts">
import { NCard, NInput, NSpace } from 'naive-ui';
import { onMounted, ref } from 'vue';
import { useSongsStore } from '@/stores/useSongsStore';
import SongTable from '@/Components/SongTable.vue';
import type { Song } from '@/types/song';

const props = defineProps<{ songs: Song[] }>();

const store = useSongsStore();
const query = ref('');

onMounted(() => {
    store.setInitial(props.songs);
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
        <n-card title="Songs">
            <n-space vertical>
                <n-input
                    :value="query"
                    placeholder="Search by title, author, tag, CCLI, key..."
                    clearable
                    @update:value="onSearch"
                />
                <song-table :songs="store.songs" :loading="store.isLoading" />
            </n-space>
        </n-card>
    </n-space>
</template>
