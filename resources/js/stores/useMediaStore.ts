import { defineStore } from 'pinia';
import { ref } from 'vue';
import type { MediaAsset } from '@/types/media';

export const useMediaStore = defineStore('media', () => {
    const assets = ref<MediaAsset[]>([]);
    const isLoading = ref(false);
    const isUploading = ref(false);
    const searchQuery = ref('');

    function setInitial(initial: MediaAsset[]): void {
        assets.value = initial;
    }

    async function search(query: string): Promise<void> {
        searchQuery.value = query;
        isLoading.value = true;

        try {
            const params = new URLSearchParams(query ? { q: query } : {});
            const response = await fetch(`/api/media?${params.toString()}`, {
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                throw new Error(`Failed to load media assets: ${response.status}`);
            }

            const body = (await response.json()) as { data: MediaAsset[] };
            assets.value = body.data;
        } finally {
            isLoading.value = false;
        }
    }

    async function upload(file: File): Promise<void> {
        const formData = new FormData();
        formData.append('file', file);

        isUploading.value = true;
        try {
            const response = await fetch('/api/media', { method: 'POST', body: formData });

            if (!response.ok) {
                throw new Error(`Failed to upload "${file.name}": ${response.status}`);
            }

            const body = (await response.json()) as { data: MediaAsset };
            assets.value = [body.data, ...assets.value];
        } finally {
            isUploading.value = false;
        }
    }

    async function remove(id: string): Promise<void> {
        const response = await fetch(`/api/media/${id}`, { method: 'DELETE' });

        if (!response.ok && response.status !== 404) {
            throw new Error(`Failed to remove media asset: ${response.status}`);
        }

        assets.value = assets.value.filter((asset) => asset.id !== id);
    }

    return { assets, isLoading, isUploading, searchQuery, setInitial, search, upload, remove };
});
