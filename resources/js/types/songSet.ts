export interface SongSetItem {
    id: string;
    songExternalId: string;
    position: number;
    transposedKey: string | null;
    notes: string | null;
    songTitle: string | null;
    songDefaultKey: string | null;
}

export interface SongSet {
    id: string;
    externalId: string;
    name: string;
    serviceDate: string | null;
    notes: string | null;
    items: SongSetItem[];
    syncedAt: string;
}
