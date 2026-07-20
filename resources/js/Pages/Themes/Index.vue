<script setup lang="ts">
import {
    NButton,
    NCard,
    NDataTable,
    NFormItem,
    NInput,
    NInputNumber,
    NPopconfirm,
    NSelect,
    NSpace,
} from 'naive-ui';
import type { DataTableColumns, SelectOption } from 'naive-ui';
import { computed, h, onMounted, ref } from 'vue';
import { useThemesStore } from '@/stores/useThemesStore';
import type { Theme, ThemeScope, TextAlign } from '@/types/theme';

const props = defineProps<{ themes: Theme[] }>();

const store = useThemesStore();
const editingId = ref<string | null>(null);
const isSaving = ref(false);
const errorMessage = ref<string | null>(null);

const scopeOptions: SelectOption[] = [
    { label: 'Global', value: 'global' },
    { label: 'Song', value: 'song' },
    { label: 'Section', value: 'section' },
];

const sectionTypeOptions: SelectOption[] = [
    { label: 'Verse', value: 'verse' },
    { label: 'Chorus', value: 'chorus' },
    { label: 'Bridge', value: 'bridge' },
    { label: 'Instrumental', value: 'instrumental' },
    { label: 'Ending', value: 'ending' },
    { label: 'Tag', value: 'tag' },
    { label: 'Pre-Chorus', value: 'pre_chorus' },
    { label: 'Custom', value: 'custom' },
];

const textAlignOptions: SelectOption[] = [
    { label: 'Left', value: 'left' },
    { label: 'Center', value: 'center' },
    { label: 'Right', value: 'right' },
];

function blankForm() {
    return {
        name: '',
        scope: 'global' as ThemeScope,
        songExternalId: '',
        sectionType: 'verse',
        backgroundColor: '',
        backgroundMediaAssetId: '',
        fontFamily: '',
        fontColor: '',
        fontSizeScale: 1,
        textAlign: 'center' as TextAlign,
    };
}

const form = ref(blankForm());

onMounted(() => {
    store.setInitial(props.themes);
});

function startEdit(theme: Theme): void {
    editingId.value = theme.id;
    form.value = {
        name: theme.name,
        scope: theme.scope,
        songExternalId: theme.songExternalId ?? '',
        sectionType: theme.sectionType ?? 'verse',
        backgroundColor: theme.backgroundColor ?? '',
        backgroundMediaAssetId: theme.backgroundMediaAssetId ?? '',
        fontFamily: theme.fontFamily ?? '',
        fontColor: theme.fontColor ?? '',
        fontSizeScale: theme.fontSizeScale,
        textAlign: theme.textAlign,
    };
    errorMessage.value = null;
}

function cancelEdit(): void {
    editingId.value = null;
    form.value = blankForm();
    errorMessage.value = null;
}

async function onSave(): Promise<void> {
    if (form.value.name.trim() === '') {
        return;
    }

    const input = {
        name: form.value.name.trim(),
        scope: form.value.scope,
        songExternalId: form.value.scope === 'song' ? form.value.songExternalId.trim() || null : null,
        sectionType: form.value.scope === 'section' ? form.value.sectionType : null,
        backgroundColor: form.value.backgroundColor.trim() || null,
        backgroundMediaAssetId: form.value.backgroundMediaAssetId.trim() || null,
        fontFamily: form.value.fontFamily.trim() || null,
        fontColor: form.value.fontColor.trim() || null,
        fontSizeScale: form.value.fontSizeScale,
        textAlign: form.value.textAlign,
    };

    isSaving.value = true;
    errorMessage.value = null;

    try {
        if (editingId.value !== null) {
            await store.update(editingId.value, input);
        } else {
            await store.create(input);
        }
        cancelEdit();
    } catch (error) {
        errorMessage.value = error instanceof Error ? error.message : 'Failed to save theme';
    } finally {
        isSaving.value = false;
    }
}

function scopeLabel(value: string): string {
    return scopeOptions.find((option) => option.value === value)?.label?.toString() ?? value;
}

const columns = computed<DataTableColumns<Theme>>(() => [
    { title: 'Name', key: 'name' },
    { title: 'Scope', key: 'scope', render: (row) => scopeLabel(row.scope) },
    { title: 'Target', key: 'target', render: (row) => row.songExternalId ?? row.sectionType ?? '—' },
    {
        title: '',
        key: 'actions',
        render: (row) => [
            h(
                NButton,
                { size: 'small', quaternary: true, onClick: () => startEdit(row) },
                { default: () => 'Edit' },
            ),
            h(
                NPopconfirm,
                { onPositiveClick: () => store.remove(row.id) },
                {
                    trigger: () => h(NButton, { size: 'small', quaternary: true }, { default: () => 'Remove' }),
                    default: () => `Remove "${row.name}"?`,
                },
            ),
        ],
    },
]);

const rowKey = (row: Theme) => row.id;
</script>

<template>
    <n-space vertical size="large">
        <n-card :title="editingId !== null ? 'Edit Theme' : 'Add a Theme'">
            <n-space vertical>
                <n-space :wrap="true">
                    <n-form-item label="Name" style="min-width: 220px">
                        <n-input v-model:value="form.name" placeholder="Sunday Morning" />
                    </n-form-item>
                    <n-form-item label="Scope" style="min-width: 180px">
                        <n-select v-model:value="form.scope" :options="scopeOptions" />
                    </n-form-item>
                    <n-form-item v-if="form.scope === 'song'" label="Song External ID" style="min-width: 220px">
                        <n-input v-model:value="form.songExternalId" placeholder="sbp-123" />
                    </n-form-item>
                    <n-form-item v-if="form.scope === 'section'" label="Section Type" style="min-width: 180px">
                        <n-select v-model:value="form.sectionType" :options="sectionTypeOptions" />
                    </n-form-item>
                </n-space>

                <n-space :wrap="true">
                    <n-form-item label="Background Color" style="min-width: 180px">
                        <n-input v-model:value="form.backgroundColor" placeholder="#1a1a2e" />
                    </n-form-item>
                    <n-form-item label="Background Media Asset ID" style="min-width: 260px">
                        <n-input v-model:value="form.backgroundMediaAssetId" placeholder="optional media asset id" />
                    </n-form-item>
                    <n-form-item label="Font Family" style="min-width: 180px">
                        <n-input v-model:value="form.fontFamily" placeholder="Inter" />
                    </n-form-item>
                    <n-form-item label="Font Color" style="min-width: 150px">
                        <n-input v-model:value="form.fontColor" placeholder="#ffffff" />
                    </n-form-item>
                    <n-form-item label="Font Size Scale" style="min-width: 140px">
                        <n-input-number v-model:value="form.fontSizeScale" :min="0.5" :max="3" :step="0.1" />
                    </n-form-item>
                    <n-form-item label="Text Align" style="min-width: 140px">
                        <n-select v-model:value="form.textAlign" :options="textAlignOptions" />
                    </n-form-item>
                </n-space>

                <n-space>
                    <n-button type="primary" :loading="isSaving" @click="onSave">
                        {{ editingId !== null ? 'Save Changes' : 'Add Theme' }}
                    </n-button>
                    <n-button v-if="editingId !== null" quaternary @click="cancelEdit">Cancel</n-button>
                </n-space>

                <n-space v-if="errorMessage" style="color: var(--n-color-error, #e88080)">
                    {{ errorMessage }}
                </n-space>
            </n-space>
        </n-card>

        <n-card title="Themes">
            <n-data-table
                :columns="columns"
                :data="store.themes"
                :loading="store.isLoading"
                :row-key="rowKey"
                :pagination="{ pageSize: 20 }"
            />
        </n-card>
    </n-space>
</template>
