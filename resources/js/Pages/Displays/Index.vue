<script setup lang="ts">
import { NButton, NCard, NDataTable, NFormItem, NInput, NPopconfirm, NSelect, NSpace } from 'naive-ui';
import type { DataTableColumns, SelectOption } from 'naive-ui';
import { computed, h, onMounted, ref } from 'vue';
import { useDisplaysStore } from '@/stores/useDisplaysStore';
import type { Display, DisplayRole } from '@/types/display';

const props = defineProps<{ displays: Display[] }>();

const store = useDisplaysStore();
const name = ref('');
const role = ref<DisplayRole>('main');
const isCreating = ref(false);

const roleOptions: SelectOption[] = [
    { label: 'Main', value: 'main' },
    { label: 'Operator', value: 'operator' },
    { label: 'Confidence Monitor', value: 'confidence_monitor' },
    { label: 'Audience', value: 'audience' },
    { label: 'Custom', value: 'custom' },
];

onMounted(() => {
    store.setInitial(props.displays);
});

async function onCreate(): Promise<void> {
    if (name.value.trim() === '') {
        return;
    }

    isCreating.value = true;
    try {
        await store.create(name.value.trim(), role.value);
        name.value = '';
        role.value = 'main';
    } finally {
        isCreating.value = false;
    }
}

function roleLabel(value: string): string {
    return roleOptions.find((option) => option.value === value)?.label?.toString() ?? value;
}

const columns = computed<DataTableColumns<Display>>(() => [
    { title: 'Name', key: 'name' },
    { title: 'Role', key: 'role', render: (row) => roleLabel(row.role) },
    { title: 'Theme', key: 'theme', render: (row) => row.settings.theme ?? '—' },
    {
        title: '',
        key: 'actions',
        render: (row) =>
            h(
                NPopconfirm,
                { onPositiveClick: () => store.remove(row.id) },
                {
                    trigger: () => h(NButton, { size: 'small', quaternary: true }, { default: () => 'Remove' }),
                    default: () => `Remove "${row.name}"?`,
                },
            ),
    },
]);

const rowKey = (row: Display) => row.id;
</script>

<template>
    <n-space vertical size="large">
        <n-card title="Add a Display">
            <n-space align="end">
                <n-form-item label="Name" style="min-width: 220px">
                    <n-input v-model:value="name" placeholder="Main Screen" @keyup.enter="onCreate" />
                </n-form-item>
                <n-form-item label="Role" style="min-width: 200px">
                    <n-select v-model:value="role" :options="roleOptions" />
                </n-form-item>
                <n-button type="primary" :loading="isCreating" @click="onCreate">Add Display</n-button>
            </n-space>
        </n-card>

        <n-card title="Displays">
            <n-data-table
                :columns="columns"
                :data="store.displays"
                :loading="store.isLoading"
                :row-key="rowKey"
                :pagination="{ pageSize: 20 }"
            />
        </n-card>
    </n-space>
</template>
