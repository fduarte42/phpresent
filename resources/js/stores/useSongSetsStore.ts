import { defineStore } from 'pinia';
import { ref } from 'vue';
import type { SongSet } from '@/types/songSet';

export const useSongSetsStore = defineStore('songSets', () => {
    const songSets = ref<SongSet[]>([]);
    const isLoading = ref(false);
    const searchQuery = ref('');

    async function search(query: string): Promise<void> {
        searchQuery.value = query;
        isLoading.value = true;

        try {
            const params = new URLSearchParams(query ? { q: query } : {});
            const response = await fetch(`/api/songsets?${params.toString()}`, {
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                throw new Error(`Failed to load song sets: ${response.status}`);
            }

            const body = (await response.json()) as { data: SongSet[] };
            songSets.value = body.data;
        } finally {
            isLoading.value = false;
        }
    }

    function setInitial(initial: SongSet[]): void {
        songSets.value = initial;
    }

    async function reorder(songSetId: string, itemIds: string[]): Promise<SongSet> {
        const response = await fetch(`/api/songsets/${songSetId}/reorder`, {
            method: 'POST',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({ itemIds }),
        });

        if (!response.ok) {
            throw new Error(`Failed to reorder song set: ${response.status}`);
        }

        const body = (await response.json()) as { data: SongSet };

        return body.data;
    }

    return { songSets, isLoading, searchQuery, search, setInitial, reorder };
});
