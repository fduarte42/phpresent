import { defineStore } from 'pinia';
import { ref } from 'vue';
import type { PresentationSession } from '@/types/presentation';

export type ConnectionStatus = 'connecting' | 'websocket' | 'sse' | 'offline';

type ControlAction = 'next' | 'previous' | 'jump' | 'blank' | 'freeze' | 'hideLyrics' | 'fontSize' | 'emergencyMessage';

/**
 * Connects to `PresentationChannel` (WebSocket) with a fallback to
 * `/sse/{displayId}` (SSE) if the WebSocket connection fails — mirrors the
 * two transports built server-side (SDD §13). Falls no further than SSE:
 * both transports already degrade gracefully (EventSource auto-reconnects
 * on the server's bounded stream duration), so a third polling fallback
 * would just be duplicating what SSE already provides.
 */
export const usePresentationStore = defineStore('presentation', () => {
    const session = ref<PresentationSession | null>(null);
    const connectionStatus = ref<ConnectionStatus>('connecting');

    let socket: WebSocket | undefined;
    let eventSource: EventSource | undefined;

    function setInitial(initial: PresentationSession): void {
        session.value = initial;
    }

    function connect(wsUrl: string, displayId: string): void {
        connectWebSocket(wsUrl, displayId);
    }

    function disconnect(): void {
        socket?.close();
        eventSource?.close();
        socket = undefined;
        eventSource = undefined;
    }

    function connectWebSocket(wsUrl: string, displayId: string): void {
        connectionStatus.value = 'connecting';
        socket = new WebSocket(wsUrl);

        socket.addEventListener('open', () => {
            connectionStatus.value = 'websocket';
        });
        socket.addEventListener('message', (event: MessageEvent<string>) => {
            applyMessage(event.data);
        });
        socket.addEventListener('close', () => {
            if (connectionStatus.value === 'websocket' || connectionStatus.value === 'connecting') {
                connectSse(displayId);
            }
        });
        socket.addEventListener('error', () => {
            socket?.close();
        });
    }

    function connectSse(displayId: string): void {
        connectionStatus.value = 'connecting';
        eventSource = new EventSource(`/sse/${displayId}`);

        eventSource.addEventListener('message', (event: MessageEvent<string>) => {
            connectionStatus.value = 'sse';
            applyMessage(event.data);
        });
        eventSource.addEventListener('error', () => {
            connectionStatus.value = 'offline';
        });
    }

    function applyMessage(raw: string): void {
        const body = JSON.parse(raw) as { data: PresentationSession };
        session.value = body.data;
    }

    async function loadSong(songId: string): Promise<void> {
        const response = await fetch('/api/presentation/load', {
            method: 'POST',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({ songId }),
        });

        if (!response.ok) {
            throw new Error(`Failed to load song: ${response.status}`);
        }

        const body = (await response.json()) as { data: PresentationSession };
        session.value = body.data;
    }

    async function control(action: ControlAction, value?: string | number | boolean | null): Promise<void> {
        const response = await fetch('/api/presentation/control', {
            method: 'POST',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({ action, value }),
        });

        if (!response.ok) {
            throw new Error(`Failed to send "${action}" command: ${response.status}`);
        }

        const body = (await response.json()) as { data: PresentationSession };
        session.value = body.data;
    }

    return {
        session,
        connectionStatus,
        setInitial,
        connect,
        disconnect,
        loadSong,
        next: () => control('next'),
        previous: () => control('previous'),
        jump: (index: number) => control('jump', index),
        setBlanked: (blanked: boolean) => control('blank', blanked),
        setFrozen: (frozen: boolean) => control('freeze', frozen),
        setLyricsHidden: (hidden: boolean) => control('hideLyrics', hidden),
        setFontSizeAdjust: (steps: number) => control('fontSize', steps),
        setEmergencyMessage: (message: string | null) => control('emergencyMessage', message),
    };
});
