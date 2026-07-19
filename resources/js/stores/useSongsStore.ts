import { defineStore } from 'pinia';
import { ref } from 'vue';
import type { Song } from '@/types/song';

export const useSongsStore = defineStore('songs', () => {
    const songs = ref<Song[]>([]);
    const isLoading = ref(false);
    const searchQuery = ref('');

    async function search(query: string): Promise<void> {
        searchQuery.value = query;
        isLoading.value = true;

        try {
            const params = new URLSearchParams(query ? { q: query } : {});
            const response = await fetch(`/api/songs?${params.toString()}`, {
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                throw new Error(`Failed to load songs: ${response.status}`);
            }

            const body = (await response.json()) as { data: Song[] };
            songs.value = body.data;
        } finally {
            isLoading.value = false;
        }
    }

    function setInitial(initial: Song[]): void {
        songs.value = initial;
    }

    return { songs, isLoading, searchQuery, search, setInitial };
});
