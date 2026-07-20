import { defineStore } from 'pinia';
import { ref } from 'vue';
import type { Display, DisplayRole, DisplaySettings } from '@/types/display';

export const useDisplaysStore = defineStore('displays', () => {
    const displays = ref<Display[]>([]);
    const isLoading = ref(false);

    function setInitial(initial: Display[]): void {
        displays.value = initial;
    }

    async function refresh(): Promise<void> {
        isLoading.value = true;

        try {
            const response = await fetch('/api/displays', { headers: { Accept: 'application/json' } });
            if (!response.ok) {
                throw new Error(`Failed to load displays: ${response.status}`);
            }

            const body = (await response.json()) as { data: Display[] };
            displays.value = body.data;
        } finally {
            isLoading.value = false;
        }
    }

    async function create(name: string, role: DisplayRole, settings?: Partial<DisplaySettings>): Promise<void> {
        const response = await fetch('/api/displays', {
            method: 'POST',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({ name, role, settings }),
        });

        if (!response.ok) {
            throw new Error(`Failed to create display: ${response.status}`);
        }

        await refresh();
    }

    async function remove(id: string): Promise<void> {
        const response = await fetch(`/api/displays/${id}`, { method: 'DELETE' });

        if (!response.ok && response.status !== 404) {
            throw new Error(`Failed to remove display: ${response.status}`);
        }

        displays.value = displays.value.filter((display) => display.id !== id);
    }

    return { displays, isLoading, setInitial, refresh, create, remove };
});
