<script setup lang="ts">
import { NAlert, NButton, NCard, NSpace } from 'naive-ui';
import { ref, useTemplateRef } from 'vue';

interface ImportResult {
    displaysImported: number;
    themesImported: number;
    mediaAssetsImported: number;
    mediaAssetsSkipped: number;
    bibleBookmarksImported: number;
    rolesImported: number;
    rolesReused: number;
    usersImported: number;
    usersSkipped: number;
}

const fileInput = useTemplateRef<HTMLInputElement>('fileInput');
const isImporting = ref(false);
const importResult = ref<ImportResult | null>(null);
const errorMessage = ref<string | null>(null);

function onPickFile(): void {
    fileInput.value?.click();
}

async function onFileSelected(event: Event): Promise<void> {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    input.value = '';

    if (file === undefined) {
        return;
    }

    errorMessage.value = null;
    importResult.value = null;
    isImporting.value = true;

    try {
        const formData = new FormData();
        formData.append('file', file);

        const response = await fetch('/api/backup/import', { method: 'POST', body: formData });
        const body = (await response.json().catch(() => null)) as
            | { data?: ImportResult; title?: string; detail?: string }
            | null;

        if (!response.ok) {
            errorMessage.value = body?.detail ?? body?.title ?? `Import failed: ${response.status}`;
            return;
        }

        importResult.value = body?.data ?? null;
    } finally {
        isImporting.value = false;
    }
}
</script>

<template>
    <n-space vertical size="large">
        <n-card title="Export">
            <n-space vertical>
                <p>
                    Downloads every table Phpresent owns locally — displays, themes, media assets
                    (including the actual files), Bible bookmarks, users, and roles. Song/Song Set
                    content is never included, since it's always re-synced from SongbookPro rather
                    than backed up.
                </p>
                <n-alert type="warning" :show-icon="false">
                    The archive contains hashed passwords (never plaintext) — treat it as sensitive as
                    a database dump.
                </n-alert>
                <a href="/api/backup/export">
                    <n-button type="primary">Export Backup</n-button>
                </a>
            </n-space>
        </n-card>

        <n-card title="Import">
            <n-space vertical>
                <p>
                    Restores from a previously exported archive. Intended for restoring into a
                    fresh/empty install: roles and users are matched by name/email and reused rather
                    than duplicated, but displays, themes, media assets, and Bible bookmarks are
                    always created fresh.
                </p>
                <input ref="fileInput" type="file" accept=".zip" style="display: none" @change="onFileSelected" />
                <n-button :loading="isImporting" @click="onPickFile">Choose Backup File...</n-button>

                <n-alert v-if="errorMessage" type="error" :show-icon="false">{{ errorMessage }}</n-alert>

                <n-alert v-if="importResult" type="success" title="Import complete" :show-icon="false">
                    <ul>
                        <li>Displays: {{ importResult.displaysImported }}</li>
                        <li>Themes: {{ importResult.themesImported }}</li>
                        <li>
                            Media assets: {{ importResult.mediaAssetsImported }} imported,
                            {{ importResult.mediaAssetsSkipped }} skipped
                        </li>
                        <li>Bible bookmarks: {{ importResult.bibleBookmarksImported }}</li>
                        <li>Roles: {{ importResult.rolesImported }} created, {{ importResult.rolesReused }} reused</li>
                        <li>
                            Users: {{ importResult.usersImported }} imported,
                            {{ importResult.usersSkipped }} skipped (already existed)
                        </li>
                    </ul>
                </n-alert>
            </n-space>
        </n-card>
    </n-space>
</template>
