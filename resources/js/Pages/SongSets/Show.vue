<script setup lang="ts">
import { NCard, NSpace, NTag } from 'naive-ui';
import { onMounted, onBeforeUnmount, ref, useTemplateRef } from 'vue';
import Sortable from 'sortablejs';
import { useSongSetsStore } from '@/stores/useSongSetsStore';
import type { SongSet } from '@/types/songSet';

const props = defineProps<{ songSet: SongSet }>();

const store = useSongSetsStore();
const items = ref([...props.songSet.items]);
const listRef = useTemplateRef<HTMLElement>('list');
let sortable: Sortable | undefined;

onMounted(() => {
    if (listRef.value === null) {
        return;
    }

    sortable = Sortable.create(listRef.value, {
        animation: 150,
        handle: '.drag-handle',
        onEnd: (event) => {
            const { oldIndex, newIndex } = event;
            if (oldIndex === undefined || newIndex === undefined || oldIndex === newIndex) {
                return;
            }

            const reordered = [...items.value];
            const [moved] = reordered.splice(oldIndex, 1);
            reordered.splice(newIndex, 0, moved);
            items.value = reordered;

            void store.reorder(props.songSet.id, reordered.map((item) => item.id)).then((updated) => {
                items.value = updated.items;
            });
        },
    });
});

onBeforeUnmount(() => {
    sortable?.destroy();
});
</script>

<template>
    <n-space vertical size="large">
        <n-card :title="songSet.name">
            <ul ref="list" style="list-style: none; padding: 0; margin: 0">
                <li
                    v-for="item in items"
                    :key="item.id"
                    style="display: flex; align-items: center; justify-content: space-between; padding: 10px 4px; border-bottom: 1px solid var(--n-border-color, #e0e0e6)"
                >
                    <n-space vertical size="small" style="min-width: 0">
                        <strong>{{ item.songTitle ?? item.songExternalId }}</strong>
                        <n-space size="small">
                            <n-tag v-if="item.transposedKey" size="small">{{ item.transposedKey }}</n-tag>
                            <span v-if="item.notes">{{ item.notes }}</span>
                        </n-space>
                    </n-space>
                    <span class="drag-handle" style="cursor: grab; padding: 0 8px">⠿</span>
                </li>
            </ul>
        </n-card>
    </n-space>
</template>
