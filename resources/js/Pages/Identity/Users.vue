<script setup lang="ts">
import {
    NAlert,
    NButton,
    NCard,
    NCheckboxGroup,
    NCheckbox,
    NDataTable,
    NFormItem,
    NInput,
    NPopconfirm,
    NSelect,
    NSpace,
    NTag,
} from 'naive-ui';
import type { DataTableColumns, SelectOption } from 'naive-ui';
import { computed, h, onMounted, ref } from 'vue';
import { useRolesStore } from '@/stores/useRolesStore';
import { useUsersStore } from '@/stores/useUsersStore';
import type { Role, User } from '@/types/identity';

const props = defineProps<{ users: User[]; roles: Role[]; forbidden: boolean }>();

const usersStore = useUsersStore();
const rolesStore = useRolesStore();

const KNOWN_PERMISSIONS = ['users.view', 'users.manage', 'roles.view', 'roles.manage'];

const roleName = ref('');
const rolePermissions = ref<string[]>([]);
const isCreatingRole = ref(false);
const roleError = ref<string | null>(null);

const userEmail = ref('');
const userPassword = ref('');
const userDisplayName = ref('');
const userRoleIds = ref<string[]>([]);
const isCreatingUser = ref(false);
const userError = ref<string | null>(null);

const assignRoleSelections = ref<Record<string, string | null>>({});

onMounted(() => {
    usersStore.setInitial(props.users);
    rolesStore.setInitial(props.roles);
});

const roleOptions = computed<SelectOption[]>(() =>
    rolesStore.roles.map((role) => ({ label: role.name, value: role.id })),
);

function roleNames(roleIds: string[]): string {
    return roleIds
        .map((id) => rolesStore.roles.find((role) => role.id === id)?.name ?? id)
        .join(', ') || '—';
}

async function onCreateRole(): Promise<void> {
    if (roleName.value.trim() === '') {
        return;
    }

    isCreatingRole.value = true;
    roleError.value = null;
    try {
        await rolesStore.create(roleName.value.trim(), rolePermissions.value);
        roleName.value = '';
        rolePermissions.value = [];
    } catch (error) {
        roleError.value = error instanceof Error ? error.message : 'Failed to create role';
    } finally {
        isCreatingRole.value = false;
    }
}

async function onCreateUser(): Promise<void> {
    if (userEmail.value.trim() === '' || userPassword.value === '') {
        return;
    }

    isCreatingUser.value = true;
    userError.value = null;
    try {
        await usersStore.create(
            userEmail.value.trim(),
            userPassword.value,
            userDisplayName.value.trim() || userEmail.value.trim(),
            userRoleIds.value,
        );
        userEmail.value = '';
        userPassword.value = '';
        userDisplayName.value = '';
        userRoleIds.value = [];
    } catch (error) {
        userError.value = error instanceof Error ? error.message : 'Failed to create user';
    } finally {
        isCreatingUser.value = false;
    }
}

async function onAssignRole(userId: string): Promise<void> {
    const roleId = assignRoleSelections.value[userId];
    if (roleId === undefined || roleId === null) {
        return;
    }

    await usersStore.assignRole(userId, roleId);
    assignRoleSelections.value[userId] = null;
}

const roleColumns: DataTableColumns<Role> = [
    { title: 'Name', key: 'name' },
    { title: 'Permissions', key: 'permissions', render: (row) => row.permissions.join(', ') || '—' },
];

const userColumns = computed<DataTableColumns<User>>(() => [
    { title: 'Email', key: 'email' },
    { title: 'Name', key: 'displayName' },
    { title: 'Roles', key: 'roles', render: (row) => roleNames(row.roleIds) },
    {
        title: 'Status',
        key: 'status',
        render: (row) => h(NTag, { type: row.isActive ? 'success' : 'error', size: 'small' }, {
            default: () => (row.isActive ? 'Active' : 'Deactivated'),
        }),
    },
    {
        title: 'Assign Role',
        key: 'assignRole',
        render: (row) =>
            h(NSpace, {}, () => [
                h(NSelect, {
                    size: 'small',
                    style: 'width: 160px',
                    options: roleOptions.value,
                    value: assignRoleSelections.value[row.id] ?? null,
                    'onUpdate:value': (value: string) => {
                        assignRoleSelections.value[row.id] = value;
                    },
                }),
                h(
                    NButton,
                    { size: 'small', onClick: () => onAssignRole(row.id) },
                    { default: () => 'Assign' },
                ),
            ]),
    },
    {
        title: '',
        key: 'actions',
        render: (row) =>
            row.isActive
                ? h(
                      NPopconfirm,
                      { onPositiveClick: () => usersStore.deactivate(row.id) },
                      {
                          trigger: () => h(NButton, { size: 'small', quaternary: true }, { default: () => 'Deactivate' }),
                          default: () => `Deactivate "${row.email}"?`,
                      },
                  )
                : null,
    },
]);

const userRowKey = (row: User) => row.id;
const roleRowKey = (row: Role) => row.id;
</script>

<template>
    <n-space vertical size="large">
        <n-alert
            v-if="forbidden"
            type="warning"
            title="You don't have permission to view this page"
        >
            Log in with an account that has users.view/roles.view permissions.
        </n-alert>

        <template v-else>
            <n-space :wrap="false" align="start" size="large">
                <n-card title="Add a Role" style="flex: 1; min-width: 0">
                    <n-space vertical>
                        <n-form-item label="Name">
                            <n-input v-model:value="roleName" placeholder="operator" @keyup.enter="onCreateRole" />
                        </n-form-item>
                        <n-form-item label="Permissions">
                            <n-checkbox-group v-model:value="rolePermissions">
                                <n-space>
                                    <n-checkbox
                                        v-for="permission in KNOWN_PERMISSIONS"
                                        :key="permission"
                                        :value="permission"
                                        :label="permission"
                                    />
                                </n-space>
                            </n-checkbox-group>
                        </n-form-item>
                        <n-button type="primary" :loading="isCreatingRole" @click="onCreateRole">Add Role</n-button>
                        <n-space v-if="roleError" style="color: #e88080">{{ roleError }}</n-space>
                    </n-space>
                </n-card>

                <n-card title="Add a User" style="flex: 1; min-width: 0">
                    <n-space vertical>
                        <n-form-item label="Email">
                            <n-input v-model:value="userEmail" placeholder="operator@church.org" />
                        </n-form-item>
                        <n-form-item label="Password">
                            <n-input v-model:value="userPassword" type="password" show-password-on="click" />
                        </n-form-item>
                        <n-form-item label="Display Name">
                            <n-input v-model:value="userDisplayName" placeholder="Optional — defaults to email" />
                        </n-form-item>
                        <n-form-item label="Roles">
                            <n-select v-model:value="userRoleIds" multiple :options="roleOptions" />
                        </n-form-item>
                        <n-button type="primary" :loading="isCreatingUser" @click="onCreateUser">Add User</n-button>
                        <n-space v-if="userError" style="color: #e88080">{{ userError }}</n-space>
                    </n-space>
                </n-card>
            </n-space>

            <n-card title="Roles">
                <n-data-table :columns="roleColumns" :data="rolesStore.roles" :row-key="roleRowKey" />
            </n-card>

            <n-card title="Users">
                <n-data-table
                    :columns="userColumns"
                    :data="usersStore.users"
                    :loading="usersStore.isLoading"
                    :row-key="userRowKey"
                    :pagination="{ pageSize: 20 }"
                />
            </n-card>
        </template>
    </n-space>
</template>
