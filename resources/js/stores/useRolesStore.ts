import { defineStore } from 'pinia';
import { ref } from 'vue';
import type { Role } from '@/types/identity';

async function parseErrorTitle(response: Response, fallback: string): Promise<string> {
    try {
        const body = (await response.json()) as { title?: string; detail?: string };
        return body.detail ?? body.title ?? fallback;
    } catch {
        return fallback;
    }
}

export const useRolesStore = defineStore('roles', () => {
    const roles = ref<Role[]>([]);
    const isLoading = ref(false);

    function setInitial(initial: Role[]): void {
        roles.value = initial;
    }

    async function refresh(): Promise<void> {
        isLoading.value = true;
        try {
            const response = await fetch('/api/roles', { headers: { Accept: 'application/json' } });
            if (!response.ok) {
                throw new Error(`Failed to load roles: ${response.status}`);
            }
            const body = (await response.json()) as { data: Role[] };
            roles.value = body.data;
        } finally {
            isLoading.value = false;
        }
    }

    async function create(name: string, permissions: string[]): Promise<void> {
        const response = await fetch('/api/roles', {
            method: 'POST',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({ name, permissions }),
        });

        if (!response.ok) {
            throw new Error(await parseErrorTitle(response, `Failed to create role: ${response.status}`));
        }

        await refresh();
    }

    return { roles, isLoading, setInitial, refresh, create };
});
