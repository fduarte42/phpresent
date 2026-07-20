<script setup lang="ts">
import { NButton, NCard, NInput, NSpace } from 'naive-ui';
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

const email = ref('');
const password = ref('');
const errorMessage = ref<string | null>(null);
const isSubmitting = ref(false);

async function onSubmit(): Promise<void> {
    errorMessage.value = null;
    isSubmitting.value = true;

    try {
        const response = await fetch('/login', {
            method: 'POST',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({ email: email.value, password: password.value }),
        });

        if (!response.ok) {
            const body = (await response.json().catch(() => null)) as { title?: string } | null;
            errorMessage.value = body?.title ?? 'Login failed';
            return;
        }

        router.visit('/');
    } finally {
        isSubmitting.value = false;
    }
}
</script>

<template>
    <n-space justify="center" style="margin-top: 60px">
        <n-card title="Log In" style="width: 360px">
            <n-space vertical>
                <n-input v-model:value="email" placeholder="Email" @keyup.enter="onSubmit" />
                <n-input
                    v-model:value="password"
                    type="password"
                    show-password-on="click"
                    placeholder="Password"
                    @keyup.enter="onSubmit"
                />
                <n-button type="primary" block :loading="isSubmitting" @click="onSubmit">Log In</n-button>
                <n-space v-if="errorMessage" style="color: #e88080">{{ errorMessage }}</n-space>
            </n-space>
        </n-card>
    </n-space>
</template>
