<script setup lang="ts">
import { NAlert, NButton, NCard, NEmpty, NInput, NInputNumber, NSpace, NSwitch, NTag } from 'naive-ui';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { usePresentationStore } from '@/stores/usePresentationStore';
import type { ConnectionStatus } from '@/stores/usePresentationStore';
import type { PresentationSession } from '@/types/presentation';
import type { Song } from '@/types/song';

const props = defineProps<{ session: PresentationSession; songs: Song[]; wsUrl: string }>();

const store = usePresentationStore();
const query = ref('');
const songs = ref<Song[]>(props.songs);
const emergencyMessageDraft = ref('');

onMounted(() => {
    store.setInitial(props.session);
    emergencyMessageDraft.value = props.session.emergencyMessage ?? '';
    store.connect(props.wsUrl, 'operator');
});

onBeforeUnmount(() => {
    store.disconnect();
});

let debounceHandle: ReturnType<typeof setTimeout> | undefined;
function onSearch(value: string): void {
    query.value = value;
    clearTimeout(debounceHandle);
    debounceHandle = setTimeout(() => {
        void searchSongs(value);
    }, 250);
}

async function searchSongs(value: string): Promise<void> {
    const params = new URLSearchParams(value ? { q: value } : {});
    const response = await fetch(`/api/songs?${params.toString()}`, { headers: { Accept: 'application/json' } });
    if (!response.ok) {
        return;
    }
    const body = (await response.json()) as { data: Song[] };
    songs.value = body.data;
}

const currentSlide = computed(() => {
    const deck = store.session?.currentDeck;
    if (deck === null || deck === undefined) {
        return null;
    }
    return deck.slides[store.session?.currentSlideIndex ?? 0] ?? null;
});

const slideCount = computed(() => store.session?.currentDeck?.slides.length ?? 0);

const statusLabel: Record<ConnectionStatus, string> = {
    connecting: 'Connecting…',
    websocket: 'Connected (WebSocket)',
    sse: 'Connected (SSE fallback)',
    offline: 'Offline — retrying…',
};

const statusType = computed<'success' | 'warning' | 'error'>(() => {
    if (store.connectionStatus === 'websocket' || store.connectionStatus === 'sse') {
        return 'success';
    }
    return store.connectionStatus === 'offline' ? 'error' : 'warning';
});

function onFontSizeChange(value: number | null): void {
    void store.setFontSizeAdjust(value ?? 0);
}

function onEmergencyShow(): void {
    void store.setEmergencyMessage(emergencyMessageDraft.value.trim() === '' ? null : emergencyMessageDraft.value);
}

function onEmergencyClear(): void {
    emergencyMessageDraft.value = '';
    void store.setEmergencyMessage(null);
}
</script>

<template>
    <n-space vertical size="large">
        <n-alert :type="statusType" :title="statusLabel[store.connectionStatus]" :show-icon="false" />
        <n-alert
            v-if="store.session?.emergencyMessage"
            type="error"
            title="Emergency message is live"
            :show-icon="false"
        >
            {{ store.session.emergencyMessage }}
        </n-alert>

        <n-space :wrap="false" align="start" size="large">
            <n-card title="Songs" style="width: 320px; flex-shrink: 0">
                <n-space vertical>
                    <n-input
                        :value="query"
                        placeholder="Search by title, author, tag..."
                        clearable
                        @update:value="onSearch"
                    />
                    <n-space vertical size="small" style="max-height: 480px; overflow-y: auto">
                        <n-button
                            v-for="song in songs"
                            :key="song.id"
                            text
                            style="justify-content: flex-start; width: 100%"
                            @click="store.loadSong(song.id)"
                        >
                            {{ song.title }}
                        </n-button>
                        <n-empty v-if="songs.length === 0" description="No songs found" size="small" />
                    </n-space>
                </n-space>
            </n-card>

            <n-space vertical size="large" style="flex: 1; min-width: 0">
                <n-card title="Current Slide">
                    <template #header-extra>
                        <n-tag v-if="slideCount > 0" size="small">
                            {{ (store.session?.currentSlideIndex ?? 0) + 1 }} / {{ slideCount }}
                        </n-tag>
                    </template>

                    <div
                        style="
                            min-height: 160px;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            text-align: center;
                            font-size: 1.4rem;
                            line-height: 1.6;
                            white-space: pre-line;
                            padding: 24px;
                            background: rgba(128, 128, 128, 0.08);
                            border-radius: 6px;
                        "
                    >
                        <span v-if="store.session?.isBlanked">— Blanked —</span>
                        <span v-else-if="currentSlide === null">No slide loaded</span>
                        <span v-else-if="store.session?.lyricsHidden">— Lyrics Hidden —</span>
                        <span v-else>{{ currentSlide.lines.join('\n') }}</span>
                    </div>

                    <n-space justify="center" style="margin-top: 16px">
                        <n-button :disabled="slideCount === 0" @click="store.previous()">Previous</n-button>
                        <n-button :disabled="slideCount === 0" @click="store.next()">Next</n-button>
                    </n-space>
                </n-card>

                <n-card title="Controls">
                    <n-space vertical size="large">
                        <n-space size="large">
                            <n-space vertical size="small" align="center">
                                <span>Blank</span>
                                <n-switch
                                    :value="store.session?.isBlanked ?? false"
                                    @update:value="store.setBlanked"
                                />
                            </n-space>
                            <n-space vertical size="small" align="center">
                                <span>Freeze</span>
                                <n-switch
                                    :value="store.session?.isFrozen ?? false"
                                    @update:value="store.setFrozen"
                                />
                            </n-space>
                            <n-space vertical size="small" align="center">
                                <span>Hide Lyrics</span>
                                <n-switch
                                    :value="store.session?.lyricsHidden ?? false"
                                    @update:value="store.setLyricsHidden"
                                />
                            </n-space>
                            <n-space vertical size="small" align="center">
                                <span>Font Size Adjust</span>
                                <n-input-number
                                    :value="store.session?.fontSizeAdjust ?? 0"
                                    :min="-5"
                                    :max="5"
                                    style="width: 110px"
                                    @update:value="onFontSizeChange"
                                />
                            </n-space>
                        </n-space>

                        <n-space vertical size="small">
                            <span>Emergency Message</span>
                            <n-space>
                                <n-input
                                    v-model:value="emergencyMessageDraft"
                                    placeholder="e.g. Please move your car, license plate ABC123"
                                    style="width: 360px"
                                    @keyup.enter="onEmergencyShow"
                                />
                                <n-button type="warning" @click="onEmergencyShow">Show</n-button>
                                <n-button
                                    quaternary
                                    :disabled="store.session?.emergencyMessage == null"
                                    @click="onEmergencyClear"
                                >
                                    Clear
                                </n-button>
                            </n-space>
                        </n-space>
                    </n-space>
                </n-card>
            </n-space>
        </n-space>
    </n-space>
</template>
