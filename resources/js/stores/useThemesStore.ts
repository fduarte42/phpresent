import { defineStore } from 'pinia';
import { ref } from 'vue';
import type { Theme, ThemeInput } from '@/types/theme';

async function parseErrorTitle(response: Response, fallback: string): Promise<string> {
    try {
        const body = (await response.json()) as { title?: string };
        return body.title ?? fallback;
    } catch {
        return fallback;
    }
}

export const useThemesStore = defineStore('themes', () => {
    const themes = ref<Theme[]>([]);
    const isLoading = ref(false);

    function setInitial(initial: Theme[]): void {
        themes.value = initial;
    }

    async function refresh(): Promise<void> {
        isLoading.value = true;

        try {
            const response = await fetch('/api/themes', { headers: { Accept: 'application/json' } });
            if (!response.ok) {
                throw new Error(`Failed to load themes: ${response.status}`);
            }

            const body = (await response.json()) as { data: Theme[] };
            themes.value = body.data;
        } finally {
            isLoading.value = false;
        }
    }

    async function create(input: ThemeInput): Promise<void> {
        const response = await fetch('/api/themes', {
            method: 'POST',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify(input),
        });

        if (!response.ok) {
            throw new Error(await parseErrorTitle(response, `Failed to create theme: ${response.status}`));
        }

        await refresh();
    }

    async function update(id: string, input: ThemeInput): Promise<void> {
        const response = await fetch(`/api/themes/${id}`, {
            method: 'PATCH',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify(input),
        });

        if (!response.ok) {
            throw new Error(await parseErrorTitle(response, `Failed to update theme: ${response.status}`));
        }

        await refresh();
    }

    async function remove(id: string): Promise<void> {
        const response = await fetch(`/api/themes/${id}`, { method: 'DELETE' });

        if (!response.ok && response.status !== 404) {
            throw new Error(`Failed to remove theme: ${response.status}`);
        }

        themes.value = themes.value.filter((theme) => theme.id !== id);
    }

    return { themes, isLoading, setInitial, refresh, create, update, remove };
});
