<script setup lang="ts">
import { NButton, NCard, NEmpty, NInput, NPopconfirm, NSpace, NTag } from 'naive-ui';
import { onMounted, ref, useTemplateRef } from 'vue';
import { useMediaStore } from '@/stores/useMediaStore';
import type { MediaAsset } from '@/types/media';

const props = defineProps<{ assets: MediaAsset[] }>();

const store = useMediaStore();
const query = ref('');
const fileInput = useTemplateRef<HTMLInputElement>('fileInput');

onMounted(() => {
    store.setInitial(props.assets);
});

let debounceHandle: ReturnType<typeof setTimeout> | undefined;
function onSearch(value: string): void {
    query.value = value;
    clearTimeout(debounceHandle);
    debounceHandle = setTimeout(() => {
        void store.search(value);
    }, 250);
}

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

    await store.upload(file);
}

function formatSize(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }
    const units = ['KB', 'MB', 'GB'];
    let value = bytes / 1024;
    let unitIndex = 0;
    while (value >= 1024 && unitIndex < units.length - 1) {
        value /= 1024;
        unitIndex += 1;
    }
    return `${value.toFixed(1)} ${units[unitIndex]}`;
}
</script>

<template>
    <n-space vertical size="large">
        <n-card title="Media Library">
            <template #header-extra>
                <input ref="fileInput" type="file" style="display: none" @change="onFileSelected" />
                <n-button type="primary" :loading="store.isUploading" @click="onPickFile">Upload</n-button>
            </template>

            <n-input
                :value="query"
                placeholder="Search by filename..."
                clearable
                style="margin-bottom: 16px"
                @update:value="onSearch"
            />

            <n-empty v-if="store.assets.length === 0" description="No media assets yet" />

            <div
                v-else
                style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 16px"
            >
                <n-card v-for="asset in store.assets" :key="asset.id" size="small" content-style="padding: 0">
                    <div
                        style="
                            aspect-ratio: 4 / 3;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            overflow: hidden;
                            background: rgba(128, 128, 128, 0.08);
                        "
                    >
                        <img
                            v-if="asset.kind === 'image'"
                            :src="`/api/media/${asset.id}/file`"
                            :alt="asset.filename"
                            style="width: 100%; height: 100%; object-fit: cover"
                        />
                        <span v-else style="font-size: 2.5rem; opacity: 0.5">
                            {{ asset.kind === 'video' ? '🎬' : asset.kind === 'audio' ? '🎵' : '📄' }}
                        </span>
                    </div>
                    <div style="padding: 10px">
                        <n-space vertical size="small">
                            <span style="font-size: 0.85rem; word-break: break-all">{{ asset.filename }}</span>
                            <n-space size="small" align="center" justify="space-between">
                                <n-tag size="small">{{ formatSize(asset.sizeBytes) }}</n-tag>
                                <n-popconfirm @positive-click="store.remove(asset.id)">
                                    <template #trigger>
                                        <n-button size="tiny" quaternary>Remove</n-button>
                                    </template>
                                    Remove "{{ asset.filename }}"?
                                </n-popconfirm>
                            </n-space>
                        </n-space>
                    </div>
                </n-card>
            </div>
        </n-card>
    </n-space>
</template>
