export interface SongSection {
    id: string;
    position: number;
    type: string;
    label: string | null;
    content: string;
}

export interface Song {
    id: string;
    externalId: string;
    title: string;
    authors: string[];
    copyright: string | null;
    ccli: string | null;
    defaultKey: string | null;
    tempo: number | null;
    capo: number | null;
    tags: string[];
    format: string;
    sections: SongSection[];
    syncedAt: string;
}
