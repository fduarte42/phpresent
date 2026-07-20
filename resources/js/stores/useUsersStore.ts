import { defineStore } from 'pinia';
import { ref } from 'vue';
import type { User } from '@/types/identity';

async function parseErrorTitle(response: Response, fallback: string): Promise<string> {
    try {
        const body = (await response.json()) as { title?: string; detail?: string };
        return body.detail ?? body.title ?? fallback;
    } catch {
        return fallback;
    }
}

export const useUsersStore = defineStore('users', () => {
    const users = ref<User[]>([]);
    const isLoading = ref(false);

    function setInitial(initial: User[]): void {
        users.value = initial;
    }

    async function refresh(): Promise<void> {
        isLoading.value = true;
        try {
            const response = await fetch('/api/users', { headers: { Accept: 'application/json' } });
            if (!response.ok) {
                throw new Error(`Failed to load users: ${response.status}`);
            }
            const body = (await response.json()) as { data: User[] };
            users.value = body.data;
        } finally {
            isLoading.value = false;
        }
    }

    async function create(email: string, password: string, displayName: string, roleIds: string[]): Promise<void> {
        const response = await fetch('/api/users', {
            method: 'POST',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({ email, password, displayName, roleIds }),
        });

        if (!response.ok) {
            throw new Error(await parseErrorTitle(response, `Failed to create user: ${response.status}`));
        }

        await refresh();
    }

    async function assignRole(userId: string, roleId: string): Promise<void> {
        const response = await fetch(`/api/users/${userId}/roles`, {
            method: 'POST',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({ roleId }),
        });

        if (!response.ok) {
            throw new Error(await parseErrorTitle(response, `Failed to assign role: ${response.status}`));
        }

        await refresh();
    }

    async function deactivate(userId: string): Promise<void> {
        const response = await fetch(`/api/users/${userId}/deactivate`, { method: 'POST' });

        if (!response.ok) {
            throw new Error(await parseErrorTitle(response, `Failed to deactivate user: ${response.status}`));
        }

        await refresh();
    }

    return { users, isLoading, setInitial, refresh, create, assignRole, deactivate };
});
